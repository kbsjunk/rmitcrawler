<html>
<head>
	<title>RMIT Crawler</title>
	<link href="//netdna.bootstrapcdn.com/bootstrap/3.1.0/css/bootstrap.min.css" rel="stylesheet">
	<script type="text/javascript" src="//code.jquery.com/jquery-1.10.2.min.js"></script>
	<script src="//netdna.bootstrapcdn.com/bootstrap/3.1.0/js/bootstrap.min.js"></script>
	<style type="text/css">
	body, table, td, th, input, textarea, label {
		font-size: 12px;
	}
	td a.extra_function {
		display:none;
	}
	td:hover a.extra_function {
		display:inline;
	}
	.big-container {
		padding:20px;
	}
	.dropdown-menu {
		font-size:12px;
	}
	.dropdown-menu>li>a {
		padding: 5px;
		line-height: 1.2;
	}
	</style>
</head>
<body>
	<div class="big-container">
		<h1>RMIT Crawler</h1>

		<form method="post" action="/rmitcrawler/crawler.php" role="form">
			<div class="form-group">
				<div class="checkbox">
					<label><input type="checkbox" name="downloadXml" value="y" checked> Download as XML?</label>
				</div>
			</div>
			<div class="form-group">
				<label class="radio-inline">
					<input type="radio" name="crawlCourses" value="program" checked> Programs
				</label>
				<label class="radio-inline">
					<input type="radio" name="crawlCourses" value="course"> Program Structures and Courses
				</label>
			</div>
			<div class="form-group">
				<textarea name="pasted" id="pasted" rows="15" class="form-control"><?php echo @$_POST['pasted']; ?></textarea>
			</div>
			<div class="form-group">
				<input type="submit" name="submit" value="Submit" class="btn btn-primary" />
			</div>
		</form>
	</div>
</body>
</html>