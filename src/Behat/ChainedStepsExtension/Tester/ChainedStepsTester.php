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

        $callResult = $result->getCallResult();

        if (null === $callResult || !$this->supportsResult($callResult)) {
            return $result;
        }

        $skip = $skip || TestResult::PASSED < $result->getResultCode();

        return $this->runChainedSteps($environment, $feature, $result, $skip);
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

    private function runChainedSteps(Environment $environment, FeatureNode $feature, HookedStepTestResult $result, $skip)
    {
        $callResult = $result->getCallResult();
        $steps = $callResult->getReturn();

        if (!is_array($steps)) {
            $steps = array($steps);
        }

        /** @var SubStep[] $steps */

        foreach ($steps as $step) {
            $result = $this->testStep($environment, $feature, $step, $skip);
            $skip = $skip || TestResult::PASSED < $result->getResultCode();
        }

        return $result;
    }
}
