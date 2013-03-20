<?php
if(count($_GET)>0) {
  $q = $_GET['q'];
  $CACHE_TIME = 60*15; /* 15 minutes */

  if(preg_match("/https?:\/\//i", $q)) {
    $hash = md5($q);
    $cache_file = "cache/".$hash;
    if(file_exists($cache_file) && (filemtime($cache_file) + $CACHE_TIME > time())) {
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
?><html>
  <head>
    <title>Feeder</title>

    <link href="http://netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/css/bootstrap-combined.min.css" rel="stylesheet"></link>
    <script src="http://code.jquery.com/jquery-1.9.1.min.js"></script>
    <script src="http://netdna.bootstrapcdn.com/twitter-bootstrap/2.3.1/js/bootstrap.min.js"></script>

    <style>
      body {
        padding-top: 60px;
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
        margin-top: 60px;
      }

      #loading-indicator {
          position: absolute;
          top: 4pt;
          left: 4pt;
          z-index: 1000;
      }

      #items {
          list-style-type: none;
      }

      .feed-name {
          overflow: hidden;
          white-space:nowrap;
          margin-right: 1em;
      }

      .unread-item {
          font-weight: bold;
      }

      .read-item {
          color: #ccc;
      }

      .read-item a {
          color: #999;
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
              <li><a id="config" href="#config-box" data-toggle="modal">Configuration</a></li>
              <li><a id="mark-all-as-read" href="#">Mark All As Read</a></li>
              <li><a id="refresh" href="#">Refresh</a></li>
              <li><a href="#about-box" data-toggle="modal">About</a></li>
            </ul>
          </div><!--/.nav-collapse -->
        </div>
      </div>
    </div>

    <div class="container" id="main">
      <ul id="items">
      </ul>
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
        <p>Feeder requires a modern browser supporting HTML5 WebSQL such as Chrome or Safari.</p>
        <p>Maybe I might update the code to use IndexedDB, if I can be bothered.</p>
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
        <form id="import-form">
          <label>Subscriptions</label><input type="file" id="xml-import"/>
        </form>
      </div>
      <div class="modal-footer">
        <a href="#" data-dismiss="modal" class="btn">Close</a>
        <button id="apply-config" class="btn btn-primary">Save changes</button>
      </div>
    </div>

    <i class="icon-refresh icon-white" id="loading-indicator"></i>

    <script type="text/javascript">
      var UPDATE_TIME = 15*60*1000;  // 15 minutes
      var db;
      var to_update = [];

      function error(heading, message) {
          var e = $('<div class="alert alert-block alert-error fade in"><button type="button" class="close" data-dismiss="alert">&times;</button><h4 class="alert-heading">'+heading+'</h4><p>'+message+'</p></div>');
          
          $('#main').prepend(e);
          $(e).alert();
          $(document.body).scrollTop(0);
      }

      function item_clicked(event) {
          db.transaction(function (tx) {                  
              tx.executeSql('update items set read_at=? where id=?', [Date.now(), event.target.id]);
          });

          $('#item-'+event.target.id).removeClass('unread-item').addClass('read-item');

          return true;
      }

      function update_feed_display(include_read) {
          var sql = "select name, i.* from items i join feeds f on f.id=i.feed_id"
          if(!include_read) {
              sql = sql + " where read_at is null";
          }
          sql = sql + " order by published_at";

          db.transaction(function (tx) {                  
              tx.executeSql(sql, [], function(tx, results) {
                  var i, L, item;
                  var items = $('#items');
                  
                  L = results.rows.length;
                  for(i=0; i<L; i++) {
                      item = results.rows.item(i);
                      
                      if(document.getElementById('item-'+item.id) == null) {
                          var klass = item.read_at ? 'read-item' : 'unread-item';
                          items.prepend($('<li id="item-'+item.id+'" class="row '+klass+'"><span class="feed-name span2">'+item.name+'</span><a href="'+item.url+'" id="'+item.id+'" tatget="_blank">'+item.title+'</a></li>'));
                          $('#'+item.id).click(item_clicked);
                      }
                  }
              });
          });
      }

      function mark_all_as_read() {
          db.transaction(function (tx) {                  
              tx.executeSql('update items set read_at=? where read_at is null', [Date.now()]);
          });

          $('li').removeClass('unread-item').addClass('read-item');
      }

      function refresh_next_feed() {
          var feed = to_update.pop();

          if(feed) {
              $.ajax({
                  url: "?q="+encodeURIComponent(feed.url),
                  cache: false,
                  success: function(data, status, req) {
                      db.transaction(function (tx) {
                          tx.executeSql('update feeds set last_update=? where id=?', [Date.now(), feed.id]);
                          var urls_seen = [];

                          $(data).find('item,entry').each(function(i, item) {
                              var title = $(item).find('title').text();
                              var url = null;
                              var published_at = Date.parse($(item).find('published,pubDate').text());
                              
                              $(item).find('link').each(function(i, link) {
                                  if($(link).attr('rel') == 'alternate') {
                                      url = $(link).attr('href');
                                  } else {
                                      if(url == null) {
                                          url = $(link).text();
                                      }
                                  }
                              });
                              
                              if(url) {
                                  urls_seen.push(url);
                                  tx.executeSql('select count(*) c from items where feed_id=? and url=?', [feed.id, url], function(tx, results) {
                                      if(results.rows.item(0).c == 0) {
                                          tx.executeSql('insert into items (feed_id, title, url, published_at) values (?,?,?,?)', [feed.id, title, url, published_at]);
                                      }
                                  }, function() { console.error(arguments) });
                              }
                          });
                      });
                  },
                  complete: function() {
                      window.setTimeout(refresh_next_feed, Math.floor(Math.random()*1000+100));
                  }
              });
          }

          update_feed_display();
      }

      function refresh_feeds() {
          if(to_update.length == 0) {
              db.transaction(function (tx) {
                  tx.executeSql('select * from feeds', [], function(_tx, results) {
                      if(results.rows == null || results.rows.length == 0) {
                          error('You have no feeds', 'Use the Configure option above to add some feeds');
                      } else {
                          var i;
                          var now = Date.now();
                          
                          for(i=0; i<results.rows.length; i++) {
                              var feed = results.rows.item(i);
                              
                              if(feed.last_update + UPDATE_TIME < now) {
                                  to_update.unshift(feed);
                              }
                          }

                          refresh_next_feed();
                      }
                  });
              });
          }
      }
      
      $(document).ready(function() {
          var xml_import_data;
          
          $('#config').click(function() {
              xml_import_data = null;
          });

          $('#mark-all-as-read').click(mark_all_as_read);
          $('#refresh').click(refresh_feeds);

          window.setInterval(refresh_feeds, 1000*60*5);

          $('#apply-config').click(function() {
              $('#config-box').modal('hide');
              if(xml_import_data) {
                  var xml = $.parseXML(xml_import_data);

                  db.transaction(function (tx) {                  
                      $(xml).find('outline').each(function(_i, e) {
                          if($(e).attr('type') == 'rss') {
                              tx.executeSql('insert into feeds (name, url, last_update) values (?, ?, 0)', [$(e).attr('title'), $(e).attr('xmlUrl')]);
                          }
                      })
                  });
              }
          });
          
          $('#xml-import').change(function (evt) {
              // check that the browser supports the file API
              if (evt.target.files === undefined) {
                  alert("Your browser doesn't support the required APIs.  Get a better browser!");
                  return undefined;
              }
              
              var f = evt.target.files[0];
              var reader = new FileReader();
              
              reader.onload = function (e) { xml_import_data = e.target.result };
              reader.readAsText(f);
          });

          if(window.openDatabase) {
              db = openDatabase('feeder', '1.0', 'Feeder DB', 10 * 1024 * 1024);
              db.transaction(function (tx) {
                  tx.executeSql('CREATE TABLE IF NOT EXISTS feeds (id INTEGER PRIMARY KEY AUTOINCREMENT, name, url, last_update integer)');
                  tx.executeSql('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, feed_id int, title, url, published_at integer, read_at integer)');
              });
              
              refresh_feeds();
          } else {
              error('Time to get a new browser', 'Your browser does not support the necessary APIs.  Get a better browser!');
          }
      });

      $(document).ajaxSend(function(event, request, settings) {
          $('#loading-indicator').show();
      });
      
      $(document).ajaxComplete(function(event, request, settings) {
          $('#loading-indicator').hide();
      });
    </script>
  </body>
</html>
