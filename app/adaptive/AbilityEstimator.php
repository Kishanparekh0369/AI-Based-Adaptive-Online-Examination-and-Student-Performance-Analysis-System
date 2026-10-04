<?php
class AbilityEstimator {
    private $theta = 0.0;
    private $thetaHistory = [];
    private $guessProbability = 0.25;

    public function update($difficulty, $wasCorrect) {
        $map = ['Easy' => -2.0, 'Medium' => 0.0, 'Hard' => 2.0];
        $d = $map[$difficulty] ?? 0.0;

        $probCorrect = $this->guessProbability + (1 - $this->guessProbability) * (1 / (1 + exp(-($this->theta - $d))));
        $adjustment = ($wasCorrect ? 1 : -1) * max(0.1, 1 - $probCorrect);

        $this->theta += $adjustment * 0.5;
        $this->thetaHistory[] = round($this->theta, 2);

        return round($this->theta, 2);
    }

    public function getTheta() {
        return round($this->theta, 2);
    }

    public function getNextDifficulty($availableCounts) {
        $difficulties = ['Easy' => $availableCounts['Easy'] ?? 0, 'Medium' => $availableCounts['Medium'] ?? 0, 'Hard' => $availableCounts['Hard'] ?? 0];
        $totalAvail = $difficulties['Easy'] + $difficulties['Medium'] + $difficulties['Hard'];
        if ($totalAvail <= 0) return 'ANY';

        $target = $this->thetaToDifficulty();

        if ($difficulties[$target] > 0) return $target;

        if ($totalAvail <= 3) {
            foreach (['Easy', 'Medium', 'Hard'] as $level) {
                if ($difficulties[$level] > 0) return $level;
            }
        }

        $order = ['Easy' => 1, 'Medium' => 2, 'Hard' => 3];
        $targetVal = $order[$target] ?? 2;
        $best = 'Medium';
        $bestDist = 999;

        foreach ($difficulties as $level => $count) {
            if ($count > 0) {
                $dist = abs($order[$level] - $targetVal);
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $best = $level;
                }
            }
        }

        return $best;
    }

    public function transitionDifficulty($currentDifficulty, $wasCorrect, $availableCounts = []) {
        $currentDifficulty = in_array($currentDifficulty, ['Easy', 'Medium', 'Hard']) ? $currentDifficulty : 'Medium';
        if ($wasCorrect) {
            $target = $currentDifficulty === 'Easy' ? 'Medium' : 'Hard';
        } else {
            $target = $currentDifficulty === 'Hard' ? 'Medium' : 'Easy';
        }

        if (($availableCounts[$target] ?? 0) > 0) {
            return $target;
        }

        foreach ([$target, 'Medium', 'Easy', 'Hard'] as $level) {
            if (($availableCounts[$level] ?? 0) > 0) {
                return $level;
            }
        }

        return 'ANY';
    }

    public function thetaToDifficulty() {
        if ($this->theta < -0.8) return 'Easy';
        if ($this->theta > 0.8) return 'Hard';
        return 'Medium';
    }

    public function getCategory() {
        if ($this->theta > 1.2) return 'Advanced';
        if ($this->theta >= -0.3) return 'Moderate';
        return 'Weak';
    }

    public function getStability() {
        $count = count($this->thetaHistory);
        if ($count < 3) return false;
        $recent = array_slice($this->thetaHistory, -3);
        $avgChange = 0;
        for ($i = 1; $i < count($recent); $i++) {
            $avgChange += abs($recent[$i] - $recent[$i - 1]);
        }
        $avgChange /= (count($recent) - 1);
        return $avgChange < 0.15;
    }

    public function loadState($theta, $history) {
        $this->theta = (float)$theta;
        if ($history) {
            $this->thetaHistory = json_decode($history, true) ?? [];
        }
    }

    public function getHistoryJson() {
        return json_encode($this->thetaHistory);
    }
}
