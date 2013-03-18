<?php
if(count($_GET)>0) {
  $q = $_GET['q'];

  if(preg_match("/https?:\/\//i", $q)) {
    $hash = md5($q);
    $cache_file = "cache/".$hash;
    if(file_exists($cache_file) && (filemtime($cache_file) + 3600 > time())) {
      $data = file_get_contents($cache_file);
    } else {
      $data = file_get_contents($q);
      if(!file_exists("cache")) {
        mkdir("cache");
      }
      file_put_contents($cache_file, $data);
    }

    header('Content-type: text/xml');

    echo $data;
    exit(0);
  } else {
    exit(500);
  }
}
?>
<html>
  <head>
    <title>Feeder</title>

    <link href="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/css/bootstrap-combined.min.css" rel="stylesheet"></link>
    <script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
    <script src="//netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/js/bootstrap.min.js"></script>

    <style>
      body {
        padding-top: 60px; /* 60px to make the container go all the way to the bottom of the topbar */
      }

      #main {
        min-height: 100%;
        height: auto !important;
        height: 100%;
        /* Negative indent footer by it's height */
        margin: 0 auto -60px;
      }

      #footer {
        height: 60px;
        background-color: #f5f5f5;
      }
    </style>
  </head>

  <body>
    <div class="navbar navbar-inverse navbar-fixed-top">
      <div class="navbar-inner">
        <div class="container">
          <a class="brand" href="#">Feeder</a>
          <div class="nav-collapse collapse">
            <ul class="nav">
              <li class="active"><a href="#">Home</a></li>
              <li><a href="#config-box" data-toggle="modal">Configuration</a></li>
              <li><a href="#about-box" data-toggle="modal">About</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>

    <div class="container" id="main">

    </div> <!-- /container -->

    <div id="footer">
      <div class="container">
        <p class="muted credit">Written by <a href="http://blog.danielparnell.com/">Daniel Parnell</a></p>
      </div>
    </div>
     
    <div class="modal hide fade" id="about-box">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h3>About Feeder</h3>
      </div>
      <div class="modal-body">
        <p>This is a simple HTML 5 single file RSS feed reader written in response to Google Reader closing down.  It contains only the features I want and nothing more.</p>
        <p>Feeder requires a modern browser supporting HTML5 such as Firefox, Chrome or Safari.</p>
      </div>
      <div class="modal-footer">
        <a href="#" data-dismiss="modal" class="btn">Close</a>
      </div>
    </div>

    <div class="modal hide fade" id="config-box">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
        <h3>Configure Feeder</h3>
      </div>
      <div class="modal-body">

      </div>
      <div class="modal-footer">
        <a href="#" data-dismiss="modal" class="btn">Close</a>
        <button class="btn btn-primary">Save changes</button>
      </div>
    </div>

    <script type="text/javascript">
      $(document).ready(function() {
        var db = openDatabase('feeder', '1.0', 'Feeder DB', 10 * 1024 * 1024);
        db.transaction(function (tx) {
          tx.executeSql('CREATE TABLE IF NOT EXISTS feeds (id INTEGER PRIMARY KEY AUTOINCREMENT, name, url, last_update integer)');
          tx.executeSql('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, feed_id int, title, url, fetched_at integer, read_at integer)');
        });
        
        // SELECT last_insert_rowid() as value
      });
    </script>
  </body>
</html>
