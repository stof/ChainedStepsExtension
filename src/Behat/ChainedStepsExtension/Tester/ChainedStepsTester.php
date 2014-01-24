<?php

/*
 * This file is part of the Behat ChainedStepsExtension.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\ChainedStepsExtension\Tester;

use Behat\Behat\Tester\HookableStepTester;
use Behat\Behat\Tester\Result\HookedStepTestResult;
use Behat\Behat\Tester\StepTester;
use Behat\Behat\Tester\Result\StepTestResult;
use Behat\Behat\Tester\Result\TestResult;
use Behat\ChainedStepsExtension\Step\SubStep;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Testwork\Call\CallResult;
use Behat\Testwork\Environment\Environment;

class ChainedStepsTester extends HookableStepTester
{
    protected function testStep(Environment $environment, FeatureNode $feature, StepNode $step, $skip)
    {
        $result = parent::testStep($environment, $feature, $step, $skip);

        return $this->processTestResult($environment, $feature, $result, $skip);
    }

    /**
     * @param Environment    $environment
     * @param FeatureNode    $feature
     * @param StepTestResult $result
     * @param boolean        $skip
     *
     * @return StepTestResult
     */
    private function processTestResult(Environment $environment, FeatureNode $feature, StepTestResult $result, $skip)
    {
        $callResult = $result->getCallResult();

        if (null === $callResult || !$this->supportsResult($callResult) || TestResult::PASSED < $result->getResultCode()) {
            return $result;
        }

        $result = $this->runChainedSteps($environment, $feature, $result, $skip);
        var_dump($result->getException(), $result->getResultCode());
        return $result;
    }

    private function supportsResult(CallResult $result)
    {
        $return = $result->getReturn();

        if ($return instanceof SubStep) {
            return true;
        }

        if (!is_array($return) || empty($return)) {
            return false;
        }

        foreach ($return as $value) {
            if (!$value instanceof SubStep) {
                return false;
            }
        }

        return true;
    }

    private function runChainedSteps(Environment $environment, FeatureNode $feature, StepTestResult $result, $skip)
    {
        $callResult = $result->getCallResult();
        $steps = $callResult->getReturn();

        if (!is_array($steps)) {
            $steps = array($steps);
        }

        /** @var SubStep[] $steps */

        $results = array($result);

        foreach ($steps as $step) {
            $results[] = $stepResult = $this->testSubStep($environment, $feature, $step, $skip);
            $skip = $skip || TestResult::PASSED < $stepResult->getResultCode();
        }

        return $this->mergeStepResults($results);
    }

    /**
     * @param Environment $environment
     * @param FeatureNode $feature
     * @param StepNode    $step
     * @param boolean     $skip
     *
     * @return StepTestResult
     */
    private function testSubStep(Environment $environment, FeatureNode $feature, StepNode $step, $skip)
    {
        $result = StepTester::testStep($environment, $feature, $step, $skip);

        return $this->processTestResult($environment, $feature, $result, $skip);
    }

    /**
     * @param StepTestResult[] $results
     *
     * @return StepTestResult
     */
    private function mergeStepResults(array $results)
    {
        $originalResult = $results[0];

        $searchException = null;
        $exception = null;
        $stdOut = null;
        $call = null;
        $return = null;

        foreach ($results as $result) {
            if (null === $searchException) {
                $searchException = $result->getSearchException();
            }

            if (null !== $callResult = $result->getCallResult()) {
                if (null === $exception) {
                    $exception = $callResult->getException();
                }

                if ($callResult->hasStdOut()) {
                    $stdOut .= $callResult->getStdOut();
                }
            }
        }

        if (null !== $originalResult->getCallResult()) {
            $call = $originalResult->getCallResult()->getCall();
            $return = $originalResult->getCallResult()->getReturn();
        }

        $finalCallResult = new CallResult($call, $return, $exception, $stdOut);

        if ($originalResult instanceof HookedStepTestResult) {
            return new HookedStepTestResult($originalResult->getSearchResult(), $searchException, $finalCallResult, $originalResult->getHookCallResults());
        }

        return new StepTestResult($originalResult->getSearchResult(), $searchException, $finalCallResult);
    }
}
