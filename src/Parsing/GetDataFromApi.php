<?php

  namespace Dota2Predict\Parsing;

  class GetDataFromApi
  {
    private $id         = 0;
    private $name       = 0;
    private $rating     = 0;
    private $countGames = 0;

    private $kills                   = 0;
    private $assists                 = 0;
    private $deaths                  = 0;
    private $kda                     = 0;
    private $hero_damage             = 0;
    private $hero_healing            = 0;
    private $total_xp                = 0;
    private $total_gold              = 0;
    private $lane_efficiency_pct     = 0;
    private $obs_placed              = 0;
    private $sen_placed              = 0;
    private $teamfight_participation = 0;
    private $observer_kills          = 0;
    private $sentry_kills            = 0;
    private $actions_per_min         = 0;
    private $winrate                 = 0;
    private $barracks_status         = 0;
    private $tower_status            = 0;
    private $bounty                  = 0;
    private $benchmarks              = 0;

    private $arrayPickBan    = [];
    private $statsApiMatches = [];
    private $lastMatches     = [];
    private $statsApiTeam    = [];
    private $matchDuration   = [];
    private $sidePerGame     = [];

    private $indexList = ['kills', 'assists', 'deaths', 'kda', 'hero_damage',
                          'hero_healing', 'total_xp', 'total_gold', 'lane_efficiency_pct',
                          'obs_placed', 'sen_placed', 'teamfight_participation',
                          'observer_kills', 'sentry_kills', 'actions_per_min'];

    public function __construct($id, $count)
    {
      $this->id           = $id;
      $this->countGames   = $count;
      $this->statsApiTeam = $this->apiQuery('https://api.opendota.com/api/teams/' . $this->id);
      $this->lastMatches  = $this->apiQuery('https://api.opendota.com/api/teams/' . $this->id . '/matches');

      $this->pars();
    }

    public function pars()
    {
      $this->findName();
      $this->findRating();
      $this->gameStatistic();
      $this->durationPerGame();
      $this->winrate();
      $this->side();
      $this->checkIndex();
      $this->checkBounty();
      $this->checkBenchmarks();
      $this->checkBuildingStatus();
      $this->picksBans();
    }

    public function findName()
    {
      $this->name = $this->statsApiTeam->name;
    }

    public function findRating()
    {
      $this->rating = $this->statsApiTeam->rating;
    }

    public function gameStatistic()
    {
      for ( $i = 0; $i < $this->countGames; $i++ )
      {
        $this->statsApiMatches[$i] = $this->apiQuery('https://api.opendota.com/api/matches/' . $this->lastMatches[$i]->match_id);
      }
    }

    public function durationPerGame()
    {
      for ( $i = 0; $i < $this->countGames; $i++ )
      {
        $this->matchDuration[$i] = $this->statsApiMatches[$i]->duration;
      }
    }

    public function side()
    {
      for ( $i = 0; $i < $this->countGames; $i++ )
      {
        if ( $this->statsApiMatches[$i]->radiant_team_id == $this->id) {
          $this->sidePerGame[$i] = 0;
        } else {
          $this->sidePerGame[$i] = 1;
        }
      }
    }

    public function winrate()
    {
      for ( $i = 0; $i < $this->countGames; $i++ )
      {
        if ( $this->lastMatches[$i]->radiant == $this->lastMatches[$i]->radiant_win) {
          $this->winrate ++;
        }
      }

      $this->winrate /= $this->countGames;

    }

    public function checkIndex()
    {
      $arrayPerMinute = array ("hero_damage", "hero_healing", "total_xp",
                               "total_gold", "obs_placed", "sen_placed",
                               "observer_kills", "sentry_kills");
      for ( $i = 0; $i < $this->countGames; $i++ ) {

        $startIndex = ($this->sidePerGame[$i] == 0) ? 0 : 5;
        foreach ($this->indexList as $key => $value) {

          for ( $j = $startIndex; $j < ($startIndex + 5); $j++ ) {

            if ( in_array($value, $arrayPerMinute) ) {
              $this->$value += $this->statsApiMatches[$i]->players[$j]->$value / floor($this->matchDuration[$i] / 60);
            } else {
              $this->$value += $this->statsApiMatches[$i]->players[$j]->$value;
            }

          }
        }
      }
    }


    public function checkBounty()
    {

      for ( $i = 0; $i < $this->countGames; $i++ ) {
        $tmpCount1 = 0;
        $tmpCount2 = 0;
        for ( $j = 0; $j < 5; $j++ ) {
          $tmpCount1 += $this->countBounty($this->statsApiMatches[$i]->players[$j]->runes);
          $tmpCount2 += $this->countBounty($this->statsApiMatches[$i]->players[$j + 5]->runes);
        }

        $tmpCount1 /= $this->matchDuration[$i];
        $tmpCount2 /= $this->matchDuration[$i];

        if ( $this->sidePerGame[$i] == 0 ) {
          $this->bounty += $tmpCount1;
        } else {
          $this->bounty += $tmpCount2;
        }
      }

    }

    public function countBounty($obj): int
    {
      $count = 0;
      $obj = (array) $obj;
      foreach ($obj as $key => $value) {
        if ($key == 5) {
          $count += $value;
        }
      }

      return $count;
    }


    public function checkBenchmarks()
    {
      $benchmarksArray = ['gold_per_min' , 'xp_per_min' , 'kills_per_min' ,
                          'last_hits_per_min' , 'hero_damage_per_min' ,
                          'hero_healing_per_min' , 'tower_damage' ,
                          'stuns_per_min' , 'lhten'];

      for ( $i = 0; $i < $this->countGames; $i++ ) {

        if ( $this->sidePerGame[$i] == 0 ) {
          for ( $j = 0; $j < 5; $j++ ) {
            foreach ($benchmarksArray as $key => $value) {
              $this->benchmarks += $this->statsApiMatches[$i]->players[$j]->benchmarks->$value->pct;
            }
          }
        } else {
          for ( $j = 5; $j < 10; $j++ ) {
            foreach ($benchmarksArray as $key => $value) {
              $this->benchmarks += $this->statsApiMatches[$i]->players[$j]->benchmarks->$value->pct;
            }
          }
        }

      }
    }

    public function checkBuildingStatus()
    {
      for ( $i = 0; $i < $this->countGames; $i++ ) {

        if ( $this->sidePerGame[$i] == 0 ) {
          $this->tower_status    += $this->statsApiMatches[$i]->tower_status_radiant;
          $this->barracks_status += $this->statsApiMatches[$i]->barracks_status_radiant;
        } else {
          $this->tower_status    += $this->statsApiMatches[$i]->tower_status_dire;
          $this->barracks_status += $this->statsApiMatches[$i]->barracks_status_dire;
        }

      }
    }

    public function picksBans()
    {

      $test = ['pick', 'ban', 'win', 'lose'];
      foreach ($test as $key => $value) {
          $this->arrayPickBan[$value] = array_fill(0, 150, 0);
      }

      for ( $i = 0; $i < $this->countGames; $i++ ) {
        foreach ($this->statsApiMatches[$i]->picks_bans as $key => $value) {
          if ( $this->sidePerGame[$i] == $this->statsApiMatches[$i]->picks_bans[$key]->team ){
            if ( $this->statsApiMatches[$i]->picks_bans[$key]->is_pick ) {
              $this->arrayPickBan['pick'][$this->statsApiMatches[$i]->picks_bans[$key]->hero_id] ++;
                if ( $this->lastMatches[$i]->radiant == $this->lastMatches[$i]->radiant_win ){
                  $this->arrayPickBan['win'][$this->statsApiMatches[$i]->picks_bans[$key]->hero_id] ++;
                } else {
                  $this->arrayPickBan['lose'][$this->statsApiMatches[$i]->picks_bans[$key]->hero_id] ++;
                }
            } else {
              $this->arrayPickBan['ban'][$this->statsApiMatches[$i]->picks_bans[$key]->hero_id] ++;
            }
          }
        }
      }
    }

    public function apiQuery(string $strQuery)
    {
      try {
        $result = json_decode(file_get_contents($strQuery));

        if ( $result === NULL )
          throw new \Exception("Error");

      } catch (Exception $e) {
          die();
      }

      return $result;
    }

    public function getStatistic(array $index): array
    {
      $result = [];
      foreach ($index as $key => $value) {
        $result[$value] = $this->$value;
      }

      return $result;
    }

    public function getPicksBans(): array
    {
      return $this->arrayPickBan;
    }

  }
