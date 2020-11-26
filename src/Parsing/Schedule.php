<?php

  namespace Dota2Predict\Parsing;

  use voku\helper\HtmlDomParser;

  class Schedule
  {
    private $link = [
      'schedule' => 'https://www.cybersport.ru/base/match?status=future&page=1&disciplines=21',
      'result'   => 'https://www.cybersport.ru/base/match?disciplines=21&status=past&page=1'
    ];

    private $schedule = array();
    private $result   = array();
    private $query    = array();

    public function __construct()
    {
      $this->pdo  = new \PDO();

      $this->prepareQuery();
      $this->getSchedule();
      $this->addNewMatches();
      $this->getResult();
      $this->closeTheMatch();
    }

    public function getSchedule()
    {
      $this->html = $this->getHtml($this->link['schedule']);
      foreach ($this->html->find('li.matches__item') as $value) {
        $tmpName1 = trim($value->find('div.matche__team--left span.d--phone-none', 0)->plaintext);
        $tmpName2 = trim($value->find('div.matche__team--right span.d--phone-none', 0)->plaintext);

        $tmpId1 = $this->getId($tmpName1);
        $tmpId2 = $this->getId($tmpName2);

        if ($tmpName1 != '' && $tmpName2 != '' && $tmpId1 != 0 && $tmpId2 != 0 && $tmpId1 != $tmpId2) {
          $this->schedule['team1Name'][] = $tmpName1;
          $this->schedule['team2Name'][] = $tmpName2;

          $this->schedule['team1Id'][] = $tmpId1;
          $this->schedule['team2Id'][] = $tmpId2;

          $this->schedule['link'][] = trim($value->find('div.matche__score a', 0)->href);

          $tmpTournament = trim($value->find('div.matche__meta a', 0)->plaintext);
          $tmpTournament = explode('|', $tmpTournament);
          $this->schedule['tournament'][] = $tmpTournament[0];

          $tmpStartDateTime = trim($value->find('div.matche__date time', 0)->datetime);
          $tmpStartDateTime = explode('T', $tmpStartDateTime);
          $this->schedule['startDate'][] = $tmpStartDateTime[0];
          $this->schedule['startTime'][] = substr($tmpStartDateTime[1], 0, -6);
        }
      }
    }

    public function addNewMatches()
    {
      foreach ($this->schedule['link'] as $key => $value){

        $this->query['findMatch']->execute([$this->schedule['link'][$key]]);

        if ($this->query['findMatch']->rowCount() == 0) {

          $tmpArray = [
            1,
            $this->schedule['team1Id'][$key],
            $this->schedule['team2Id'][$key],
            $this->schedule['team1Name'][$key],
            $this->schedule['team2Name'][$key],
            $this->schedule['link'][$key],
            $this->schedule['tournament'][$key],
            $this->schedule['startDate'][$key],
            $this->schedule['startTime'][$key]
          ];

          $this->query['insert']->execute($tmpArray);
        }
      }
    }

    public function getResult()
    {
      $this->html = $this->getHtml($this->link['result']);
      foreach ($this->html->find('li.matches__item') as $value) {
        $tmpName1 = trim($value->find('div.matche__team--left span.d--phone-none', 0)->plaintext);
        $tmpName2 = trim($value->find('div.matche__team--right span.d--phone-none', 0)->plaintext);

        $tmpId1 = $this->getId($tmpName1);
        $tmpId2 = $this->getId($tmpName2);

        if ($tmpName1 != '' && $tmpName2 != '' && $tmpId1 != 0 && $tmpId2 != 0) {
          $this->result['team1Name'][] = $tmpName1;
          $this->result['team2Name'][] = $tmpName2;

          $this->result['team1Id'][] = $tmpId1;
          $this->result['team2Id'][] = $tmpId2;

          $this->result['link'][] = trim($value->find('div.matche__score a', 0)->href);

          $this->result['score'][] = str_replace(' ', '', $value->find('div.matche__score', 0)->plaintext);
        }
      }
    }

    public function closeTheMatch()
    {
      foreach ($this->result['link'] as $key => $value){

        $this->query['openedMatch']->execute([$this->result['link'][$key]]);
        if ($this->query['openedMatch']->rowCount() == 1) {

          foreach ($this->query['openedMatch']->fetchAll() as $item){

            $tmpWinner = $this->getWinner($this->result['score'][$key]);
            $this->query['closeTheMatch']->execute([
              $tmpWinner,
              $this->result['score'][$key],
              $this->result['link'][$key]
            ]);

          }

        }

      }
    }

    public function prepareQuery()
    {
      $this->query =
        [
          'getId'         => $this->pdo->prepare('SELECT * FROM teams WHERE name = ? ORDER BY team_id LIMIT 1'),
          'openedMatch'   => $this->pdo->prepare('SELECT * FROM predict WHERE statusUpdate = 2 AND link = ?'),
          'findMatch'     => $this->pdo->prepare('SELECT * FROM predict WHERE link = ?'),
          'closeTheMatch' => $this->pdo->prepare('UPDATE predict SET statusUpdate = 3, winner = ?, score = ? WHERE link = ?'),
          'insert'        => $this->pdo->prepare('INSERT INTO predict (game, team1Id, team2Id, team1Name, team2Name, link, tournament, startDate, startTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'),
        ];
    }

    public function getId(string $name): int
    {
      $result = 0;
      $this->query['getId']->execute([$name]);
      foreach ($this->query['getId']->fetchAll() as $key => $value){
        $result = $value['team_id'];
      }

      return $result;
    }

    public function getHtml(string $link)
    {
      $result = HtmlDomParser::file_get_html($link);

      try {
        if (!$result) {
          throw new Exception('Error');
        } else {
          return $result;
        }
      } catch (Exception $e) {
        die();
      }
    }

    public function getWinner(string $score): int
    {
      $tmpResult = explode(':', $score);
      $winner = 0;
      if ($tmpResult[0] != $tmpResult[1]) {
        $winner = ($tmpResult[0] > $tmpResult[1]) ? 1 : 2 ;
      }

      return $winner;
    }

  }
