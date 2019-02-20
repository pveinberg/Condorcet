<?php
/*
    Condorcet PHP - Election manager and results calculator.
    Designed for the Condorcet method. Integrating a large number of algorithms extending Condorcet. Expandable for all types of voting systems.

    By Julien Boudry and contributors - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/
declare(strict_types=1);


namespace CondorcetPHP\Condorcet\Algo;

use CondorcetPHP\Condorcet\CondorcetVersion;
use CondorcetPHP\Condorcet\Election;
use CondorcetPHP\Condorcet\Timer\Chrono as Timer_Chrono;

class Pairwise implements \ArrayAccess, \Iterator
{
    use CondorcetVersion;

    // Implement ArrayAccess
    public function offsetSet($offset, $value) : void {}

    public function offsetExists($offset) : bool {
        return isset($this->_Pairwise[$offset]);
    }

    public function offsetUnset($offset) : void {}

    public function offsetGet($offset) : ?array {
        return $this->_Pairwise[$offset] ?? null;
    }


    // Implement Iterator
    private $valid = true;

    public function rewind() : void {
        reset($this->_Pairwise);
        $this->valid = true;
    }

    public function current() : array {
        return $this->_Pairwise[$this->key()];
    }

    public function key() : ?int {
        return key($this->_Pairwise);
    }

    public function next() : void {
        if (next($this->_Pairwise) === false) :
            $this->valid = false;
        endif;
    }

    public function valid() : bool {
        return $this->valid;
    }   


    // Pairwise

    protected $_Election;
    protected $_Pairwise = [];

    public function __construct (Election &$link)
    {
        $this->setElection($link);
        $this->doPairwise();
    }

    public function __clone ()
    {
        $this->_Election = null;
    }

    public function setElection (Election $election) : void
    {
        $this->_Election = $election;
    }

    public function getExplicitPairwise () : array
    {
        $explicit_pairwise = [];

        foreach ($this->_Pairwise as $candidate_key => $candidate_value) :

            $candidate_name = $this->_Election->getCandidateObjectFromKey($candidate_key)->getName();
            
            foreach ($candidate_value as $mode => $mode_value) :

                foreach ($mode_value as $candidate_list_key => $candidate_list_value) :
                    $explicit_pairwise[$candidate_name][$mode][$this->_Election->getCandidateObjectFromKey($candidate_list_key)->getName()] = $candidate_list_value;
                endforeach;

            endforeach;

        endforeach;

        return $explicit_pairwise;
    }

    protected function doPairwise () : void
    {
        // Chrono
        new Timer_Chrono ( $this->_Election->getTimerManager(), 'Do Pairwise' );

        $this->formatNewpairwise();

        // Win && Null
        foreach ( $this->_Election->getVotesManager() as $vote_id => $oneVote ) :

            // Ignore vote who don't respect election constraints
            if(!$this->_Election->testIfVoteIsValidUnderElectionConstraints($oneVote)) :
                continue;
            endif;

            $vote_ranking = $oneVote->getContextualRanking($this->_Election);

            $voteWeight = ($this->_Election->isVoteWeightIsAllowed()) ? $oneVote->getWeight() : 1;

            $vote_candidate_list = (function (array $r) : array { $list = [];
                    foreach ($r as $rank) :
                        foreach ($rank as $oneCandidate) :
                            $list[] = $oneCandidate;
                        endforeach;
                    endforeach;

                    return $list;})($vote_ranking);

            $done_Candidates = [];

            foreach ($vote_ranking as $candidates_in_rank) :

                $candidates_in_rank_keys = [];

                foreach ($candidates_in_rank as $candidate) :
                    $candidates_in_rank_keys[] = $this->_Election->getCandidateKey($candidate);
                endforeach;

                foreach ($candidates_in_rank as $candidate) :

                    $candidate_key = $this->_Election->getCandidateKey($candidate);

                    // Process
                    foreach ( $vote_candidate_list as $g_Candidate ) :

                        $g_candidate_key = $this->_Election->getCandidateKey($g_Candidate);

                        if ($candidate_key === $g_candidate_key) :
                            continue;
                        endif;

                        // Win & Lose
                        if (    !in_array($g_candidate_key, $done_Candidates, true) && 
                                !in_array($g_candidate_key, $candidates_in_rank_keys, true) ) :

                            $this->_Pairwise[$candidate_key]['win'][$g_candidate_key] += $voteWeight;
                            $this->_Pairwise[$g_candidate_key]['lose'][$candidate_key] += $voteWeight;

                            $done_Candidates[] = $candidate_key;

                        // Null
                        elseif (in_array($g_candidate_key, $candidates_in_rank_keys, true)) :
                            $this->_Pairwise[$candidate_key]['null'][$g_candidate_key] += $voteWeight;
                        endif;

                    endforeach;

                endforeach;

            endforeach;

        endforeach;
    }

    protected function formatNewpairwise () : void
    {
        foreach ( $this->_Election->getCandidatesList(false) as $candidate_key => $candidate_id ) :

            $this->_Pairwise[$candidate_key] = [ 'win' => [], 'null' => [], 'lose' => [] ];

            foreach ( $this->_Election->getCandidatesList(false) as $candidate_key_r => $candidate_id_r ) :

                if ($candidate_key_r !== $candidate_key) :
                    $this->_Pairwise[$candidate_key]['win'][$candidate_key_r]   = 0;
                    $this->_Pairwise[$candidate_key]['null'][$candidate_key_r]  = 0;
                    $this->_Pairwise[$candidate_key]['lose'][$candidate_key_r]  = 0;
                endif;

            endforeach;

        endforeach;
    }
}
