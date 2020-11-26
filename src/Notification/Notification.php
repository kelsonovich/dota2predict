<?php

  namespace Dota2Predict;

    class Notification
    {
        private $token = [
                'tg' => '',
                'vk' => ''
              ];
        private $groupId = [
                'tg' => '',
                'vk' => ''
            ];
        private $link    = array();
        private $query   = array();
        private $message = array();

        public function __construct()
        {
            $this->pdo = new \PDO();

            $this->prepareQuery();
            $this->predict();
            $this->result();
            $this->send();
        }

        public function prepareQuery()
        {
            $this->link = [
              'tg' => 'https://api.telegram.org/bot' . $this->token['tg'] . '/sendMessage?',
              'vk' => 'https://api.vk.com/method/wall.post?'
            ];

            $this->query = [
              'result'  => $this->pdo->prepare('SELECT * FROM predict WHERE statusUpdate = 3 AND statusSend = 2 ORDER BY startDate ASC, startTime ASC LIMIT 5'),
              'predict' => $this->pdo->prepare('SELECT * FROM predict WHERE statusUpdate = 2 AND statusSend = 0'),
              'updateP' => $this->pdo->prepare('UPDATE predict SET statusSend = 1 WHERE link = ?'),
              'updateR' => $this->pdo->prepare('UPDATE predict SET statusSend = 2 WHERE link = ?')
            ];
        }

        public function predict()
        {
            $this->query['predict']->execute();
            foreach ($this->query['predict']->fetchAll() as $value){
                $tmpUnixDateTime  = strtotime($value['startDate'] . " " . $value['startTime']) + 60 * 60 * 3;
                $tmpStartDateTime = date('H:i d.m' , $tmpUnixDateTime);

                $tmpName    = $value['team1Name'] . ' vs. ' . $value['team2Name'];

                $tmpPredict = ( $value['predict'] == 1 ) ? $value['team1Name'] : $value['team2Name'];
                $tmpPredict = 'Победа '  . $tmpPredict . ' - ' . $value['chance'] . '%';

                $tmpTournament = explode('.', $value['tournament']);
                $tmpTournament = str_replace('Dota 2', '', $tmpTournament[0]);

                $this->message[] = $this->convertDateTime($tmpUnixDateTime) . '<br>' .
                    $tmpName . '<br>' .
                    $tmpPredict . '<br>' .
                    $tmpTournament;

               $this->query['updateP']->execute([$value['link']]);
            }
        }

        public function result()
        {
            $this->query['result']->execute();
            foreach ($this->query['result']->fetchAll() as $value){
                $tmpUnixDateTime  = strtotime($value['startDate'] . " " . $value['startTime']) + 60 * 60 * 3;
                $tmpStartDateTime = date('H:i d.m' , $tmpUnixDateTime);

                $tmpMark = '';
                $tmpMark = ( $value['winner'] == $value['predict'] ) ? ' ✅' : ' ❌' ;

                $tmpScore = str_replace(':', ' - ', $value['score']);
                $tmpName  = $value['team1Name'] . ' ' . $tmpScore . ' ' . $value['team2Name'];

                $tmpPredict = ( $value['predict'] == 1 ) ? $value['team1Name'] : $value['team2Name'];
                $tmpPredict = 'Победа '  . $tmpPredict . ' - ' . $value['chance'] . '%';

                $tmpTournament = explode('.', $value['tournament']);
                $tmpTournament = str_replace('Dota 2', '', $tmpTournament[0]);


                $this->message[] = $this->convertDateTime($tmpUnixDateTime) . $tmpMark . '<br>' .
                    $tmpName . '<br>' .
                    $tmpPredict . '<br>' .
                    $tmpTournament;

               $this->query['updateR']->execute([$value['link']]);
            }
        }

        public function send()
        {
            foreach ($this->message as $value){
                $value = str_replace('<br>', "\n", $value);
                $dataTG = [
                  'chat_id'                  => $this->groupId['tg'],
                  'text'                     => $value,
                  'disable_web_page_preview' => True,
                ];
                $dataVK = [
                  'owner_id'     => $this->groupId['vk'],
                  'from_group'   => 1,
                  'message'      => $value,
                  'access_token' => $this->token['vk'],
                  'v' => 5.122
                ];
               $sendingTG = file_get_contents($this->link['tg'] . http_build_query($dataTG) , false);
               $sendingVK = file_get_contents($this->link['vk'] . http_build_query($dataVK), false);
            }
        }

        public function convertDateTime($unix)
        {
            $tmpTime  = date('H:i' , $unix);
            $tmpDay   = date('j' , $unix);
            $tmpMonth = date('m' , $unix);

            switch ($tmpMonth){
                case '01': $result = 'Января';   break;
                case '02': $result = 'Февраля';  break;
                case '03': $result = 'Марта';    break;
                case '04': $result = 'Апреля';   break;
                case '05': $result = 'Мая';      break;
                case '06': $result = 'Июня';     break;
                case '07': $result = 'Июля';     break;
                case '08': $result = 'Августа';  break;
                case '09': $result = 'Сентября'; break;
                case '10': $result = 'Октября';  break;
                case '11': $result = 'Ноября';   break;
                case '12': $result = 'Декабря';  break;
            }

            return $tmpDay . ' ' . $result . ' в ' . $tmpTime ;
        }

    }
