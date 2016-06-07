<?php
namespace Nexmo\Gorillas;

use Nexmo\Message\Text;

class Game
{
    /**
     * @var \Nexmo\Client
     */
    protected $nexmo;

    /**
     * @var \Pubnub\Pubnub
     */
    protected $pubnub;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var \MongoClient
     */
    protected $mongo;

    /**
     * @var \MongoCollection
     */
    protected $players;

    /**
     * @var \MongoCollection
     */
    protected $games;

    /**
     * @var \MongoDB
     */
    protected $db;

    protected $responses = [
        'Bananas!',
        'Got it!',
        ':8]'
    ];

    public function __construct($dsn, \Nexmo\Client $nexmo, \Pubnub\Pubnub $pubnub, $channel)
    {
        $this->mongo   = new \MongoClient($dsn);
        $this->nexmo   = $nexmo;
        $this->pubnub  = $pubnub;
        $this->channel = $channel;

        $this->db = $this->mongo->selectDB('gorillas');
        $this->games = $this->db->selectCollection('games');
        $this->players = $this->db->selectCollection('players');
    }

    public function control($message)
    {
        error_log(json_encode($_POST, JSON_PRETTY_PRINT));
        if(!isset($message['action']) OR !isset($message['game'])){
            error_log('no control message found');
            return;
        }

        switch($message['action']){
            /**
             * Game ended, remove looser, increment winner.
             */
            case 'win':
                $this->addWin($message['player']);
                break;
            case 'replace':
                $this->endPlayer($message['game'], $message['player']);
                break;
        }

        return [
            'game' => $this->getGameWithPlayers($message['game']),
            'leaders' => $this->getLeaders()
        ];
    }

    public function process(\Nexmo\Message\InboundMessage $inboundMessage)
    {
        $command = $inboundMessage->getBody();
        $game    = $inboundMessage->getTo();
        $player  = $inboundMessage->getFrom();

        error_log('got inbound message to game: ' . $game);
        error_log('from: ' . $player);
        error_log($command);

        /**
         * Sending an email adds you to the play queue, and sends you a help text.
         */
        if($email = filter_var($inboundMessage->getBody(), FILTER_VALIDATE_EMAIL)){
            error_log('email found, adding to queue if not in queue');
            $this->addPlayer($game, $player, $email);
            $this->nexmo->message()->send($inboundMessage->createReply("You're in the queue, watch the screen then play."));
            return;
        }

        /**
         * Sending stop, ends your game or takes you out of the play queue.
         */
        switch(trim(strtolower($command))){
            case 'stop':
                $this->endPlayer($game, $player);
                $this->nexmo->message()->send($inboundMessage->createReply('You have left the game.'));
                return;
        }

        /**
         * If the player is active, send to browser. It sorts out who can go next.
         */
        if($this->isPlaying($game, $player)){
            error_log('got command from current player');
            if($message = $this->play($game, $player, $command)){
                $this->nexmo->message()->send($inboundMessage->createReply($message));
            }
            return;
        }

        $this->nexmo->message()->send($inboundMessage->createReply('SMS your email address to be added to the queue.'));
    }

    /**
     * Add the player to the play queue for a game.
     */
    public function addPlayer($game, $player, $email)
    {
        $this->games->update(['_id' => $game], ['$addToSet' => ['queue' => $player]], ['upsert' => true]);
        $this->players->update(['_id' => $player], ['$set' => ['email' => $email]], ['upsert' => true]);

        $this->sendState($game, [
            'player' => $this->getPlayer($player),
            'action' => 'add'
        ]);

    }

    /**
     * Remove the player form the game's play queue.
     */
    public function endPlayer($game, $player)
    {
        $this->games->update([
            '_id' => $game
        ],[
            '$pull' => [
                'queue' => $player,
                'playing' => $player
            ]
        ]);

        $this->sendState($game, [
            'player' => $this->getPlayer($player),
            'action' => 'end'
        ]);
    }

    /**
     * Is this player playing the game currently?
     */
    public function isPlaying($game, $player)
    {
        $data = $this->getGame($game);

        if(in_array($player, $data['playing'])){
            return true;
        }
    }

    public function getGame($game)
    {
        $data = $this->games->findOne(['_id' => $game]);

        if(!$data){
            return [
                'queue' => [],
                'playing' => []
            ];
        }

        //advance players if needed
        while(count($data['playing']) < 2 AND count($data['queue']) > 0){
            $next = reset($data['queue']);
            $this->nexmo->message()->send(new Text($next, $game, 'Your turn!'));
            $data = $this->games->findAndModify([
                '_id' => $game
            ],[
                '$addToSet' => ['playing' => $next],
                '$pull' => [
                    'queue' => $next,
                ]
            ]);
        }

        return $data;
    }

    public function getLeaders()
    {
        $leaders =  $this->players->find([])->sort([
            'score' => -1
        ]);

        return array_values(iterator_to_array($leaders));
    }

    public function getGameWithPlayers($game)
    {
        $data = $this->getGame($game);
        foreach($data['queue'] as $index => $player){
            $data['queue'][$index] = $this->getPlayer($player);
        }

        foreach($data['playing'] as $index => $player){
            $data['playing'][$index] = $this->getPlayer($player);
        }

        return $data;
    }

    public function addWin($player)
    {
        $this->players->update([
            '_id' => $player
        ],[
            '$inc' => ['score' => 1]
        ]);

        return $this->getPlayer($player);
    }

    public function getPlayer($player)
    {
        return $this->players->findOne(['_id' => $player]);
    }

    public function play($game, $player, $command)
    {
        if(!preg_match('#(\d+)\D+(\d+)#', $command, $match)){
            return 'Please reply with an angle (1-90) and a velocity (1-100)';
        }

        if($match[1] < 1 or $match[1] > 90) {
            return 'Angle must be between 1 and 90';
        }

        if($match[1] < 1 or $match[2] > 100) {
            return 'Velocity must be between 1 and 90';
        }

        $this->sendState($game, [
            'angle' => $match[1],
            'velocity' => $match[2],
            'number' => $player,
            'player' => $this->getPlayer($player),
            'action' => 'throw'
        ]);

        $this->games->update([
            '_id' => $game
        ],[
            '$set' => ['touch' => time()]
        ]);

        return $this->responses[array_rand($this->responses)];
    }

    protected function sendState($game, $command = [])
    {
        $message = [
            'command' => $command,
            'game' => $this->getGameWithPlayers($game),
            'leaders' => $this->getLeaders()
        ];

        error_log('sending to: ' . $this->channel . $game);
        error_log(json_encode($message));

        $this->pubnub->publish($this->channel . $game, $message);
    }
}