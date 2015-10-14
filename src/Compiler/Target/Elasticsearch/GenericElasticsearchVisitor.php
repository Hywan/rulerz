<?php

namespace RulerZ\Compiler\Target\Elasticsearch;

use Hoa\Ruler\Model as AST;

use RulerZ\Compiler\Target\GenericVisitor;
use RulerZ\Model;
use RulerZ\Compiler\Target\Polyfill;

/**
 * Base class for Elasticsearch-related visitors.
 */
abstract class GenericElasticsearchVisitor extends GenericVisitor
{
    use Polyfill\AccessPath;

    /**
     * @inheritDoc
     */
    public function visitAccess(AST\Bag\Context $element, &$handle = null, $eldnah = null)
    {
        $dimensions = $element->getDimensions();

        // nested path
        if (!empty($dimensions)) {
            return $this->flattenAccessPath($element);
        }

        return $element->getId();
    }

    /**
     * {@inheritDoc}
     */
    public function visitParameter(Model\Parameter $element, &$handle = null, $eldnah = null)
    {
        return sprintf('$parameters["%s"]', $element->getName());
    }

    /**
     * @inheritDoc
     */
    protected function defineBuiltInOperators()
    {
        // start with a few helpers
        $must = function($query) {
            return [
                'bool' => ['must' => $query]
            ];
        };
        $mustNot = function($query) {
            return [
                'bool' => ['must_not' => $query]
            ];
        };
        $range = function($field, $value, $operator) use ($must) {
            return $must([
                'range' => [
                    $field => [$operator => $value],
                ]
            ]);
        };

        // Here are the operators!
        $this->setInlineOperator('and', function ($a, $b) use ($must) {
            return $must([$a, $b]);
        });
        $this->setInlineOperator('or', function ($a, $b) use ($must) {
            return [
                'bool' => ['should' => [$a, $b], 'minimum_should_match' => 1]
            ];
        });

        $this->setInlineOperator('like', function ($a, $b) use ($must) {
            return $must([
                'match' => [
                    $a => is_array($b) ? implode(' ', $b) : $b,
                ]
            ]);
        });
        $this->setInlineOperator('has', function ($a, $b) use ($must) {
            return $must([
                'terms' => [
                    $a => is_array($b) ? $b : [$b],
                ]
            ]);
        });
        $this->setInlineOperator('in', $this->getInlineOperator('has'));

        $this->setInlineOperator('=', function ($a, $b) use ($must) {
            return $must([
                'term' => [
                    $a => $b,
                ]
            ]);
        });

        $this->setInlineOperator('not', $mustNot);

        $this->setInlineOperator('match_all', function() {
            return ['match_all' => []];
        });

        $this->setInlineOperator('>', function ($a, $b) use ($range) {
            return $range($a, $b, 'gt');
        });

        $this->setInlineOperator('>=', function ($a, $b) use ($range) {
            return $range($a, $b, 'gte');
        });

        $this->setInlineOperator('<', function ($a, $b) use ($range) {
            return $range($a, $b, 'lt');
        });

        $this->setInlineOperator('<=', function ($a, $b) use ($range) {
            return $range($a, $b, 'lte');
        });

        $this->setInlineOperator('!=', function ($a, $b) use ($mustNot) {
            return $mustNot([
                'terms' => [
                    $a => is_array($b) ? $b : [$b],
                ]
            ]);
        });

        $this->setInlineOperator('in_envelope', function ($a, $b) use ($must) {
            return $must([
                'geo_shape' => [
                    $a => [
                        'shape' => [
                            'type'        => 'envelope',
                            'coordinates' => $b,
                        ]
                    ]
                ]
            ]);
        });
    }
}
