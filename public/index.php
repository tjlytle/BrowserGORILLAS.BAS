<?php
$config = include __DIR__ . '/../init.php';
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
				var last;

				prompt = function(text){
					if(!text){
						text = '';
					}
					$('#prompt').html('SMS an angle (1-90) and a speed (1-100) to <?php echo $config['number'] ?>. ' + text)
				};

				pubnub = PUBNUB({
					publish_key   : '<?php echo $config['pubnub']['publish_key'] ?>',
					subscribe_key : '<?php echo $config['pubnub']['subscribe_key'] ?>'
				})

				pubnub.subscribe({
					channel: '<?php echo $config['channel'] ?>',
					message: function(message){
						if(last == message.from){
							console.log('got back to back commands');
							prompt('<br/> Hey, let the other player go.');
							return;
						}

						last = message.from;

						console.log(message);
						api.setAng(api.turn, message.angle);
						api.setVel(api.turn, message.velocity);
						api.throwBanana();
						prompt();
					}
				});

				prompt();

			});
		</script>
	</head>
	<body>
		<div id="game">
			<div class="playerInfo" id="info1">
				<span class="name active">Player 1</span><br/>
				<div class="launch">
					<span class="label">Angle:</span> <span class="angle">45</span><br/>
					<span class="label">Speed:</span> <span class="velocity">50</span><br/>
					<span class="label">Score:</span> <span class="score">0</span>
				</div>
			</div>
			
			<div class="playerInfo" id="info2">
				<span class="name">Player 2</span><br/>
				<div class="launch">
					<span class="label">Angle:</span> <span class="angle">45</span><br/>
					<span class="label">Speed:</span> <span class="velocity">50</span><br/>
					<span class="label">Score:</span> <span class="score">0</span>
				</div>
			</div>
			<div id="prompt"></div>
		</div>
	</body>
</html>
