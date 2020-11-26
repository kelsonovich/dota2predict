<?php
  namespace Dota2Predict\Parsing;

  class UpdateTeamName
  {
    private $link = [
        'fromApi' => 'https://api.opendota.com/api/teams',
    ];

    private $teamListFromApi  = array();
    private $teamListFromBase = array();
    private $query            = array();

    public function __construct()
    {
      $this->pdo  = new \PDO();

      $this->prepareQuery();
      $this->getTeamListFromApi();
      $this->getTeamListFromBase();
      $this->compareAndUpdate();
    }

    public function getTeamListFromApi()
    {
      $this->teamListFromApi = $this->getDataFromApi($this->link['fromApi']);
    }

    public function getTeamListFromBase()
    {
      $this->query['teamList']->execute();
      foreach ($this->query['teamList']->fetchAll() as $value){
        $this->teamListFromBase[$value['team_id']] = [
          'name' => $value['name'],
          'tag'  => $value['tag']
        ];
      }
    }

    public function compareAndUpdate()
    {
      foreach ($this->teamListFromApi as $key => $value){

        if (array_key_exists($value['team_id'], $this->teamListFromBase)) {

          if ($value['name'] !== $this->teamListFromBase[$value['team_id']]['name'] ||
              $value['tag']  !== $this->teamListFromBase[$value['team_id']]['tag']) {
                $tmpArray = [
                  $value['name'],
                  $value['tag'],
                  $value['team_id']
                ];
                $this->query['update']->execute($tmpArray);
              }

        } else {
          if (strlen($value['name']) > 0){
            $tmpArray = [
              $value['team_id'],
              $value['name'],
              $value['tag']
            ];
            $this->query['insert']->execute($tmpArray);
          }
        }

      }
    }

    public function prepareQuery()
    {
      $this->query = [
        'teamList' => $this->pdo->prepare('SELECT * FROM teams'),
        'insert'   => $this->pdo->prepare('INSERT INTO teams (team_id, name, tag) VALUES (?, ?, ?)'),
        'update'   => $this->pdo->prepare('UPDATE teams SET name = ?, tag = ? WHERE team_id = ?'),
      ];
    }

    public function getDataFromApi(string $strQuery): array
    {
      try {
        $result = json_decode(file_get_contents($strQuery), true);

        if ( $result === NULL )
          throw new Exception("Error");

      } catch (Exception $e) {
        die();
      }

      return $result;
    }
  }
