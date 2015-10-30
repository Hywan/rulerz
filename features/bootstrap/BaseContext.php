<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

abstract class BaseContext implements Context
{
    /**
     * @var \RulerZ\RulerZ
     */
    protected $rulerz;

    /**
     * @var mixed
     */
    protected $dataset;

    /**
     * @var array
     */
    protected $parameters = [];

    /**
     * @var array
     */
    protected $executionContext = [];

    /**
     * @var mixed
     */
    protected $results;

    /**
     * Returns the compilation target to be tested.
     *
     * @return \RulerZ\Compiler\Target\CompilationTarget
     */
    abstract protected function getCompilationTarget();

    /**
     * Returns the default dataset to be filtered.
     *
     * @return mixed
     */
    abstract protected function getDefaultDataset();

    /**
     * Create a default execution context that will be given to RulerZ when
     * filtering a dataset.
     *
     * @return mixed
     */
    protected function getDefaultExecutionContext()
    {
        return [];
    }

    /**
     * @Given RulerZ is configured
     */
    public function rulerzIsConfigured()
    {
        // compiler
        $compiler = new \RulerZ\Compiler\EvalCompiler(new \RulerZ\Parser\HoaParser());

        // RulerZ engine
        $this->rulerz = new \RulerZ\RulerZ($compiler, [$this->getCompilationTarget()]);
    }

    /**
     * @When I define the parameters:
     */
    public function iDefineTheParameters(TableNode $parameters)
    {
        $this->parameters = $parameters->getRowsHash();
    }

    /**
     * @When I use the default execution context
     */
    public function iUseTheDefaultExecutionContext()
    {
        $this->executionContext = $this->getDefaultExecutionContext();
    }

    /**
     * @When I use the default dataset
     */
    public function iUseTheDefaultDataset()
    {
        $this->dataset = $this->getDefaultDataset();
    }

    /**
     * @When I filter the dataset with the rule:
     */
    public function iFilterTheDatasetWithTheRule(PyStringNode $rule)
    {
        $this->results = $this->rulerz->filter($this->dataset, (string) $rule, $this->parameters, $this->executionContext);

        $this->parameters       = [];
        $this->executionContext = [];
    }

    /**
     * @Then I should have the following results:
     */
    public function iShouldHaveTheFollowingResults(TableNode $table)
    {
        if (count($table->getHash()) !== count($this->results)) {
            throw new \RuntimeException(sprintf("Expected %d results, got %d. Expected:\n%s\nGot:\n%s", count($table->getHash()), count($this->results), $table, var_export($this->results, true)));
        }

        foreach ($table as $row) {
            foreach ($this->results as $result) {
                $objectResult = is_array($result) ? (object) $result : $result;

                if ($objectResult->pseudo === $row['pseudo']) {
                    return;
                }
            }

            throw new \RuntimeException(sprintf('Player "%s" not found in the results.', $row['pseudo']));
        }
    }
}