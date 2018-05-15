<?php
/*
    Part of INSTANT-RUNOFF method Module - From the original Condorcet PHP

    Condorcet PHP - Election manager and results calculator.
    Designed for the Condorcet method. Integrating a large number of algorithms extending Condorcet. Expandable for all types of voting systems.

    By Julien Boudry and contributors - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/
declare(strict_types=1);

namespace Condorcet\Algo\Methods;

use Condorcet\Algo\Method;
use Condorcet\Algo\MethodInterface;

use Condorcet\Result;

class InstantRunoff extends Method implements MethodInterface
{
    // Method Name
    public const METHOD_NAME = ['Instant-runoff','InstantRunoff','preferential voting','ranked-choice voting','alternative vote','AlternativeVote','transferable vote','Vote alternatif'];

    public static $starting = 1;

    protected $_Stats;

    protected function getStats () : array
    {
        $stats = [];

        return $stats;
    }


/////////// COMPUTE ///////////

    //:: Alternative Vote ALGORITHM. :://

    protected function compute () : void
    {
        $candidateCount = $this->_selfElection->countCandidates();
        $candidateDone = [];
        $result = [];
        $CandidatesWinnerCount = 0;
        $CandidatesLoserCount = 0;

        while (count($candidateDone) < $candidateCount) :
            $score = [];
            foreach ($this->_selfElection->getCandidatesList() as $oneCandidate) :
                if (!in_array($this->_selfElection->getCandidateKey($oneCandidate),$candidateDone,true)) :
                    $score[$this->_selfElection->getCandidateKey($oneCandidate)] = 0;
                endif;
            endforeach;

            foreach ($this->_selfElection->getVotesManager() as $oneVote) :
                $weight = ($this->_selfElection->isVoteWeightIsAllowed()) ? $oneVote->getWeight() : 1;

                for ($i = 0 ; $i < $weight ; $i++) :
                    $oneRanking = $oneVote->getContextualRanking($this->_selfElection);

                    foreach ($oneRanking as $oneRank) :
                        foreach ($oneRank as $oneCandidate) :
                            if(count($oneRank) !== 1) :
                                break;
                            elseif (!in_array($this->_selfElection->getCandidateKey($oneCandidate),$candidateDone,true)) :
                                $score[$this->_selfElection->getCandidateKey(reset($oneRank))] += 1;
                                break 2;
                            endif;
                        endforeach;
                    endforeach;
                endfor;

            endforeach;

            $maxScore = max($score);
            $minScore = min($score);

            if ($maxScore > $this->_selfElection->countVotes()) :
                $rank = $CandidatesWinnerCount + 1;
                foreach ($score as $candidateKey => $candidateScore) :
                    if ($candidateScore !== $maxScore) :
                        continue;
                    else :
                        $result[$rank][] = $candidateKey;
                        $candidateDone[] = $candidateKey;
                        $CandidatesWinnerCount++;
                    endif;
                endforeach;
            else :
                $rank = $candidateCount - $CandidatesLoserCount;
                foreach ($score as $candidateKey => $candidateScore) :
                    if ($candidateScore !== $minScore) :
                        continue;
                    else :
                        $result[$rank][] = $candidateKey;
                        $candidateDone[] = $candidateKey;
                        $CandidatesLoserCount++;
                    endif;
                endforeach;
            endif;

        endwhile;

        $this->_Result = $this->createResult($result);
    }

    protected function tieBreaking (array $candidatesKeys) : int
    {

    }
}