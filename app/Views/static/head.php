<head>
	<meta charset="UTF-8">
	<title>DOM Cloud Hosting</title>
	<link rel="shortcut icon" href="/logo.svg" />
	<meta name="description" content="The small framework with powerful features">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta1/dist/css/bootstrap.min.css">
	<?php if (ENVIRONMENT === 'production') : ?>
		<script src="https://google.com/recaptcha/api.js" async defer></script>
	<?php endif ?>

	<style>
		form {
			border-radius: 10px;
			-webkit-backdrop-filter: blur(10px);
			backdrop-filter: blur(10px);
			box-sizing: content-box;
			width: calc(100% - 30px) !important;
			border: 2px solid whitesmoke;
			background: #0003;
		}

		form a {
			font-weight: bold;
			color: var(--light);
		}

		.signin-group>button {
			width: 100%;
			margin-bottom: .5em;
			background: white;
		}


		.text-shadow {
			text-shadow: 0px 0px 2px black;
		}

		.floating {
			position: absolute;
			left: 10px;
			bottom: 10px;
		}

		.floating a {
			color: #fff6;
			transition: color 0.2s;
		}

		.floating a:hover {
			color: white;
		}

		.separator {
			display: flex;
			align-items: center;
			text-align: center;
			opacity: .8;
		}

		.separator::before,
		.separator::after {
			content: '';
			flex: 1;
			border-bottom: 1px solid white;
		}

		.separator::before {
			margin-right: .5em;
		}

		.separator::after {
			margin-left: .5em;
		}
	</style>
	<script>
		window.addEventListener('DOMContentLoaded', (event) => {
			var browser = navigator.userAgent.toLowerCase();
			if (browser.indexOf('firefox') > -1) {
				document.getElementsByTagName("form")[0].style.background = document.getElementsByTagName("body")[0].style.backgroundColor;
			}
		});
	</script>
</head>