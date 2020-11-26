<?php

  namespace Dota2Predict\Predict;

  use Dota2Predict\Parsing\GetDataFromApi;

  class CompareIndex
  {
    private $team1;
    private $team2;

    private $team1Statistic = [];
    private $team2Statistic = [];
    private $team1PicksBans = [];
    private $team2PicksBans = [];

    private $statistics = '';
    private $picksBans  = '';

    private $countGames = 20;
    private $matchId    = 0;
    private $predict    = 0;
    private $chance     = 0;
    private $indexList = ['rating', 'winrate', 'kills', 'assists', 'deaths', 'kda', 'hero_damage',
                          'hero_healing', 'total_xp', 'total_gold', 'lane_efficiency_pct',
                          'obs_placed', 'sen_placed', 'teamfight_participation',
                          'observer_kills', 'sentry_kills', 'actions_per_min'];

    public function __construct()
    {
      $this->pdo  = new \PDO();

      $this->prepareQuery();
      $this->getMatchForCompare();
      $this->getStatistic();
      $this->getPicksBans();
      $this->mainCompare();
      $this->statsToString();
      $this->picksBansToString();
      $this->updateInBase();
    }

    public function getMatchForCompare()
    {
      $this->query['getMatchForCompare']->execute();
      foreach ($this->query['getMatchForCompare']->fetchAll() as $key => $value) {

        $tmpCount1 = count(json_decode(file_get_contents('https://api.opendota.com/api/teams/' . $value['team1Id'] . '/matches')));
        $tmpCount2 = count(json_decode(file_get_contents('https://api.opendota.com/api/teams/' . $value['team2Id'] . '/matches')));

        if ( $tmpCount1 < 20 || $tmpCount2 < 20 ) {
            $this->countGames = min($tmpCount1, $tmpCount2);
        }

        if ( $this->countGames < 5 ) {
            $this->query['setStatusU4']->execute([$value['id']]);
        } else {
          $this->team1 = new GetDataFromApi($value['team1Id'], $this->countGames);
          $this->team2 = new GetDataFromApi($value['team2Id'], $this->countGames);
          $this->matchId = $value['id'];
        }
      }
    }

    public function mainCompare()
    {
      $tmpCountTeam1 = 0;
      $tmpCountTeam2 = 0;

      foreach ($this->indexList as $key => $value) {
        $team1Value = round($this->team1Statistic[$value] / $this->countGames, 5);
        $team2Value = round($this->team2Statistic[$value] / $this->countGames, 5);

        if ( $value == "deaths" ) {
          if ( $team1Value != $team2Value ) {
            if ( $team1Value > $team2Value ) {
              $tmpCountTeam2 ++;
            } else {
              $tmpCountTeam1 ++;
            }
          }
        } else {
          if ( $team1Value != $team2Value ) {
            if ( $team1Value > $team2Value ) {
              $tmpCountTeam1 ++;
            } else {
              $tmpCountTeam2 ++;
            }
          }
        }
      }
      $tmpChanceTeam1 = round( 100 * ( $tmpCountTeam1 * ( $this->team1Statistic['rating'] / $this->team2Statistic['rating'] ) ) /
        (( $tmpCountTeam1 * ( $this->team1Statistic['rating'] / $this->team2Statistic['rating'] ) ) +
        ( $tmpCountTeam2 * ( $this->team2Statistic['rating'] / $this->team1Statistic['rating'] ) ) ) );
      $tmpChanceTeam2 = round( 100 * ( $tmpCountTeam2 * ( $this->team2Statistic['rating'] / $this->team1Statistic['rating'] ) ) /
        (( $tmpCountTeam1 * ( $this->team1Statistic['rating'] / $this->team2Statistic['rating'] ) ) +
        ( $tmpCountTeam2 * ( $this->team1Statistic['rating'] / $this->team1Statistic['rating'] ) ) ) );

      if ( $tmpChanceTeam1 > $tmpChanceTeam2 ) {
        $this->predict = 1;
        $this->chance  = $tmpChanceTeam1;
      } else {
        $this->predict = 2;
        $this->chance  = $tmpChanceTeam2;
      }
    }

    public function statsToString()
    {
      foreach ($this->indexList as $key => $value) {
        $this->statistics .= $value . "*" . round($this->team1Statistic[$value], 5)
          . "*" . round($this->team2Statistic[$value], 5) . "#";
      }
    }

    public function picksBansToString()
    {
      $tmpIndex = ['pick', 'ban', 'win', 'lose'];
      foreach ($tmpIndex as $key => $value) {
        asort($this->team1PicksBans[$value]);
        asort($this->team2PicksBans[$value]);
      }

      foreach ($tmpIndex as $key => $value) {
        for ( $i = 0; $i < 20 ; $i++) {

          //for PHP 7.3 > 'array_pop( array_keys(' change to 'array_key_last'
          $this->picksBans .= array_pop( array_keys($this->team1PicksBans[$value])) . "-" .
                                 array_pop($this->team1PicksBans[$value]) . "-" .
                                 array_pop( array_keys($this->team2PicksBans[$value])) . "-" .
                                 array_pop($this->team2PicksBans[$value]) . "/" ;
        }
        $this->picksBans .= '#';
      }
    }

    public function updateInBase()
    {
      $this->query['setPredict']->execute([
        $this->team1Statistic['rating'],
        $this->team2Statistic['rating'],
        $this->predict,
        $this->chance,
        $this->statistics,
        $this->picksBans,
        $this->matchId
      ]);
    }

    public function prepareQuery()
    {
      $this->query = [
        'getMatchForCompare' => $this->pdo->prepare('SELECT * FROM predict_3 WHERE statusUpdate = 1 LIMIT 1'),

        'setStatusU4' => $this->pdo->prepare('UPDATE predict_3 SET statusUpdate = 4 WHERE id = ?'),

        'setPredict' => $this->pdo->prepare('UPDATE predict_3 SET team1Rating = ?,
          team2Rating = ?, predict = ?, chance = ?, predictData = ?,
          pickBan = ?, statusUpdate = 2 WHERE id = ?')
      ];
    }

    public function getPicksBans()
    {
      $this->team1PicksBans = $this->team1->getPicksBans();
      $this->team2PicksBans = $this->team2->getPicksBans();
    }

    public function getStatistic()
    {
      $this->team1Statistic = $this->team1->getStatistic($this->indexList);
      $this->team2Statistic = $this->team2->getStatistic($this->indexList);
    }

  }
