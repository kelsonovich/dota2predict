<?php
  namespace Dota2Predict\Parsing;

  use voku\helper\HtmlDomParser;

  class CheckChanges {

    private $fromBase  = [];
    private $paramList = ['team1Id', 'team2Id', 'team1Name', 'team2Name', 'link', 'tournament', 'statusUpdate', 'statusSend', 'startDate', 'startTime'];

    public function __construct()
    {
        $this->pdo  = new \PDO();

        $this->prepareQuery();
        $this->getFromBase();
        $this->compare();
    }

    public function compare()
    {
        foreach($this->fromBase as $value){

            $html = $this->getPage('https://www.cybersport.ru' . $value['link']);
            if ($this->checkClosedMatch($html)) {
                $tmpFromPage = $this->getFromPage($html);

                $tmpCurrentTime = time();
                $tmpStartTime   = strtotime($value['startDate'] . ' ' . $value['startTime']) + 60 * 60 * 3;

                if ($tmpCurrentTime > $tmpStartTime) {

                    $tmp = $this->checkChanges($value, $tmpFromPage);

                    if ($tmp[0]) {
                        $this->toClose($value['link']);
                    }

                } elseif (($tmpCurrentTime + 60 * 60) > $tmpStartTime) {

                    $tmp = $this->checkChanges($value, $tmpFromPage);

                    if (!$tmp[0] && !$tmp[1]) {

                    } elseif ($tmp[0]) {
                        $this->toPredict($value['link']);
                        $this->toSend($value['link']);
                    } elseif ($tmp[1]) {

                    }

                    if ($value['statusUpdate'] == 0){
                        $this->toPredict($value['link']);
                    }

                } elseif (($tmpCurrentTime + 60 * 60 * 2) > $tmpStartTime) {
                    $tmp = $this->checkChanges($value, $tmpFromPage);

                    if (!$tmp[0] && !$tmp[1]) {
                        if ($value['statusUpdate'] == 0) {
                            $this->toPredict($value['link']);
                        }
                    } elseif ($tmp[0]) {
                        $this->toPredict($value['link']);
                    }

                } else {
                    $this->checkChanges($value, $tmpFromPage);
                }

            }
        }
    }

    public function checkChanges(array $fromBase, array $fromPage): array
    {
        $flag = [false, false];
        $tmpCheckIdName = $this->checkIdName($fromBase, $fromPage);
        $tmpCheckTDT    = $this->сheckTDT($fromBase, $fromPage);
        if ($tmpCheckIdName) {
            $this->query['updateIdName']->execute([
              $fromPage['team1Id'],
              $fromPage['team2Id'],
              $fromPage['team1Name'],
              $fromPage['team2Name'],
              $fromBase['link']
            ]);
            $flag[0] = true;
        }
        if ($tmpCheckTDT) {
            $this->query['updateTDT']->execute([
              $fromPage['tournament'],
              $fromPage['startDate'],
              $fromPage['startTime'],
              $fromBase['link']
            ]);
            $flag[1] = true;
        }

        return $flag;
    }

    public function checkIdName(array $fromBase, array $fromPage): bool
    {
        if ($fromPage['team1Id'] != $fromPage['team2Id'] && $fromPage['team1Name'] != $fromPage['team2Name']) {

            if ($fromPage['team1Id']   != $fromBase['team1Id'] ||
                $fromPage['team2Id']   != $fromBase['team2Id'] ||
                $fromPage['team1Name'] != $fromBase['team1Name'] ||
                $fromPage['team2Name'] != $fromBase['team2Name']) {
                return true;
            } else {
                return false;
            }

        }
    }

    public function сheckTDT(array $fromPage, array $fromBase): bool
    {
        if ($fromPage['tournament'] != $fromBase['tournament'] ||
            $fromPage['startDate']  != $fromBase['startDate'] ||
            $fromPage['startTime']  != $fromBase['startTime']) {
            return true;
        } else {
            return false;
        }
    }

    public function getFromBase()
    {
        $this->query['getMatchList']->execute();
        foreach ($this->query['getMatchList']->fetchAll() as $key => $value){
            foreach ($this->paramList as $item) {
                $this->fromBase[$key][$item] = $value[$item];
            }
        }
    }

    public function getFromPage($html): array
    {
        $fromPage = [];
        foreach ($html->find('div.cb-container') as $value){
            $fromPage['team1Name'] = trim($value->find('div.match-duel-team__name', 0)->plaintext);
            $fromPage['team2Name'] = trim($value->find('div.match-duel-team__name', 1)->plaintext);

            $fromPage['team1Id'] = $this->getId($fromPage['team1Name']);
            $fromPage['team2Id'] = $this->getId($fromPage['team2Name']);

            $tmpTournament = explode('|', trim($value->find("div.match-duel-header", 0)->plaintext));
            $fromPage['tournament'] = trim($tmpTournament[1]);

            $tmpDateTime = explode('T', trim($value->find("time.match-duel-header__time", 0)->datetime));
            $fromPage['startDate'] = $tmpDateTime[0];
            $fromPage['startTime'] = substr($tmpDateTime[1], 0, 8);
        }

        return $fromPage;
    }

    public function checkClosedMatch($html): bool
    {
        $tmpTitle = 'Cybersport.ru - киберспорт и игры, новости, турниры, расписание матчей, рейтинги команд и игроков';
        $tmpFind  = trim($html->find('head title', 0)->plaintext);
        $result   = ($tmpFind == $tmpTitle) ? false : true ;

        return $result;
    }

    private function getPage(string $link)
    {
        $this->html = HtmlDomParser::file_get_html($link);

        try {
            if ( !$this->html ) {
                throw new Exception('Error');
            } else {
                return $this->html;
            }
        } catch (Exception $e) {
            die();
        }
    }

    private function getId(string $name): int
    {
        $result = 0;
        // because 'prepareQuery' for this method dosn't working
        $this->query['getId'] = $this->pdo->prepare('SELECT * FROM teams2 WHERE name = ? OR shortname = ?');
        $this->query['getId']->execute([$name, $name]);
        foreach ($this->query['getId']->fetchAll() as $value){
            $result = $value['team_id'];
        }

        return $result;
    }

    private function prepareQuery()
    {
        $this->query =
            [
                'updateIdName' => $this->pdo->prepare('UPDATE predict SET team1Id = ?, team2Id = ?, team1Name = ?, team2Name = ? WHERE link = ?'),
                'updateTDT'    => $this->pdo->prepare('UPDATE predict SET tournament = ?, startDate = ?, startTime = ? WHERE link = ?'),

                'setStatusU1'  => $this->pdo->prepare('UPDATE predict SET statusUpdate = 1 WHERE link = ?'),
                'setStatusS0'  => $this->pdo->prepare('UPDATE predict SET statusSend = 0 WHERE link = ?'),

                'getMatchList' => $this->pdo->prepare('SELECT * FROM predict WHERE statusUpdate < 3 ORDER BY startDate ASC, startTime ASC '),
                'toClose'      => $this->pdo->prepare('UPDATE predict SET statusUpdate = 7 WHERE link = ?'),
                'getId'        => $this->pdo->prepare('SELECT * FROM teams WHERE name = ? OR shortname = ?')
            ];
    }

    private function toClose(string $link)
    {
        $this->query['toClose']->execute([$link]);
    }

    private function toPredict(string $link)
    {
        $this->query['setStatusU1']->execute([$link]);
    }

    private function toSend(string $link)
    {
        $this->query['setStatusS0']->execute([$link]);
    }

}
