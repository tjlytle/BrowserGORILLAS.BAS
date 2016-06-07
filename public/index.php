<?php
$config = include __DIR__ . '/../init.php';
$config['channel'] .= $config['number'];

?>
<html lang="en">
	<head>
		<title>Demo</title>
		<!--<meta name="viewport" content="width=640;" />-->
		<link type="text/css" href="css/pepper-grinder/jquery-ui-1.8.13.custom.css" rel="stylesheet" />
		<link type="text/css" href="css/gorillas.css" rel="stylesheet" />
		<script type="text/javascript" src="js/2D.js"></script>
		<script type="text/javascript" src="js/Intersection.js"></script>
	    <script type="text/javascript" src="js/jquery-1.6.1.min.js"></script>
		<script type="text/javascript" src="js/jquery-ui-1.8.13.custom.min.js"></script>
		<script type="text/javascript" src="js/jquery.ui.touch.js"></script>
		<script type="text/javascript" src="js/gorillas.js"></script>
		<script src="http://cdn.pubnub.com/pubnub-3.7.15.min.js"></script>
		<script type="text/javascript">
			$(document).ready(function(){
				var game = '<?php echo $config['number'] ?>';
				var lastNumber;
				var lostNumber;

				var prompt = function(text){
					if(!text){
						text = '';
					}
					$('#prompt').html('SMS an angle (1-90) and a velocity (1-100) to ' + game + '. ' + text)
				};

				var paintUI = function(data){
					console.log(data);
					for (index = 0; index < data.game.playing.length; ++index) {
						$('#info' + (index + 1) + ' span.name').text(data.game.playing[index].email);
						$('#info' + (index + 1) + ' span.score').text(data.game.playing[index].score ? data.game.playing[index].score : '0');
					}

					$('#leader span.data').html('');
					for (index = 0; index < data.leaders.length; ++index) {
						$('#leader span.data').append('<p class="player">' + data.leaders[index].email + ': <span class="score">' + (data.leaders[index].score ? data.leaders[index].score : '0')  + '</span></p>')
					}

					$('#queue span.data').html('');
					for (index = 0; index < data.game.queue.length; ++index) {
						$('#queue span.data').append('<p class="player">' + data.game.queue[index].email + '</p>')
					}

				};

				var currentPlayer = function(){
					return $('#info' + api.turn + ' span.name').text();
				};

				var tryCommand = function(command){
					console.log('trying command');
					console.log(command);
					if(currentPlayer() == command.player.email){
						api.setAng(api.turn, command.angle);
						api.setVel(api.turn, command.velocity);
						api.throwBanana();
						lostNumber = lastNumber; //looser is never the current throw
						lastNumber = command.player._id;
						prompt();
					} else {
						//let the next player go right after
						console.log('storing next command');
						api.onNextTurn(function(){
							api.onNextTurn(); //clear callback
							tryCommand(command);
						});
					}
				};

				pubnub = PUBNUB({
					publish_key   : '<?php echo $config['pubnub']['publish_key'] ?>',
					subscribe_key : '<?php echo $config['pubnub']['subscribe_key'] ?>'
				})

				$.ajax({
					url: 'control.php',
					type: "POST",
					data: {
						game: game,
						action: 'init'
					},
					success: function(data) {
						paintUI(data);
					}
				});

				pubnub.subscribe({
					channel: '<?php echo $config['channel'] ?>',
					message: function(message){
						console.log(message);
						if(message.command && message.command.action == 'throw'){
							tryCommand(message.command);
						}

						paintUI(message);
					}
				});

				api.onWin(function(player){
					console.log('got win callback');
					api.onNextTurn(); //clear any queued commands
					$.ajax({
						url: 'control.php',
						type: "POST",
						data: {
							game: game,
							action: 'win',
							player: lastNumber
						},
						success: function(data) {
							//get next player
							if(data.game.queue.length){
								$.ajax({
									url: 'control.php',
									type: "POST",
									data: {
										game: game,
										action: 'replace',
										player: lostNumber
									},
									success: function(data) {
											paintUI(data);
									}
								});
							} else {
								paintUI(data);
							}
						}
					});					
					
				});

				prompt();

			});
		</script>
	</head>
	<body>
		<div id="queue">
			Next Players:
			<span class="data"></span>
		</div>
		<div id="leader">
			Leader Board:
			<span class="data"></span>
		</div>
		<div id="game">
			<div class="playerInfo" id="info1">
				<span class="name active">Player 1</span><br/>
				<div class="launch">
					<span class="label">Angle:</span> <span class="angle">45</span><br/>
					<span class="label">Velocity:</span> <span class="velocity">50</span><br/>
					<span class="label">Score:</span> <span class="score">0</span>
				</div>
			</div>
			
			<div class="playerInfo" id="info2">
				<span class="name">Player 2</span><br/>
				<div class="launch">
					<span class="label">Angle:</span> <span class="angle">45</span><br/>
					<span class="label">Velocity:</span> <span class="velocity">50</span><br/>
					<span class="label">Score:</span> <span class="score">0</span>
				</div>
			</div>
			<div id="prompt"></div>
		</div>
	</body>
</html>
