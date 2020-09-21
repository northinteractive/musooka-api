<?php

class Ranking {

    private function _score($upvotes = 0, $downvotes = 0) {
        return $upvotes - $downvotes;
    }

    private function _hotness($upvotes = 0, $downvotes = 0, $posted = 0) {
        $s = $this->_score($upvotes, $downvotes);
        $order = log(max(abs($s), 1), 10);

        if($s > 0) {
            $sign = 1;
        } elseif($s < 0) {
            $sign = -1;
        } else {
            $sign = 0;
        }

        $seconds = $posted - 1134028003;

        return round(($order + $sign * $seconds), 7);
    }

    private function _confidence($upvotes = 0, $downvotes = 0) {
        $n = $upvotes + $downvotes;

        if($n === 0) {
            return 0;
        }

        $z = 1.281551565545; // 80% confidence
        $p = floor($upvotes) / $n;

        $left = $p + 1/(2*$n)*$z*$z;
        $right = $z*sqrt($p*(1-$p)/$n + $z*$z/(4*$n*$n));
        $under = 1+1/$n*$z*$z;

        return ($left - $right) / $under;
    }

    public function controversy($upvotes = 0, $downvotes = 0) {
        return ($upvotes + $downvotes) / max(abs($this->_score($upvotes, $downvotes)), 1);
    }


    public function hotness($upvotes, $downvotes, $posted) {
        return $this->_hotness($upvotes, $downvotes, $posted);
    }

    public function confidence($upvotes, $downvotes) {
        return $this->_confidence($upvotes, $downvotes);
    }
}