<?php
if(count($_POST)>0) {
    header('Content-type: application/json');
    $cmd = $_POST['cmd'];
    $db = new SQLite3("db/db.sqlite");

    if($cmd == 'init') {
        $db->exec('CREATE TABLE IF NOT EXISTS feeds (id INTEGER PRIMARY KEY AUTOINCREMENT, name, url, last_update integer)');
        $db->exec('CREATE TABLE IF NOT EXISTS items (id INTEGER PRIMARY KEY AUTOINCREMENT, feed_id int, title, url, published_at integer, read_at integer)');
        $result = "OK";
    } else if($cmd == "all_read") {
        $db->exec("update items set read_at=".time()." where read_at is null");
        $result = "OK";
    } else if($cmd == "add") {
        $feed_id = intval($_POST["feed_id"]);
        $items = json_decode($_POST["items"]);
        $result = 0;
        $db->exec('update feeds set last_update='.time().' where id='.$feed_id);

        while($item = array_pop($items)) {
            $title = $db->escapeString($item[0]);
            $url = $db->escapeString($item[1]);
            $published_at = intval($item[2]);

            $count = $db->querySingle("select count(*) c from items where feed_id=".$feed_id." and url='".$url."'");
            if($count == 0) {
                $sql = "insert into items (feed_id, title, url, published_at) values (".$feed_id.",'".$title."','".$url."',".$published_at.")";
                // error_log($sql);
                $db->exec($sql);
                $result++;
            }
        }
    } else if($cmd == "read") {
        $db->exec("update items set read_at=".time()." where id=".intval($_POST["item_id"]));
        $result = "OK";
    } else if($cmd == "add_feeds") {
        $feeds = json_decode($_POST['feeds']);
        $result = 0;
        while($feed = array_pop($feeds)) {
            $title = $db->escapeString($feed[0]);
            $url = $db->escapeString($feed[1]);

            $db->exec("insert into feeds (name, url, last_update) values ('".$title."', '".$url."', 0)");
        }
    } else {
        $result = "UNKNOWN COMMAND";
    }

    echo json_encode($result);
    exit(0);
} else if(count($_GET)>0) {
    if(array_key_exists('q', $_GET)) {
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
    } else {
        header('Content-type: application/json');
        $cmd = $_GET['cmd'];
        $db = new SQLite3("db/db.sqlite");

        if($cmd == "feeds") {
            $sql = "select * from feeds";
        } else if($cmd == "items") {
            $sql = "select name, i.* from items i join feeds f on f.id=i.feed_id";
            $search = $_GET['search'];
            if($search) {
                $sql = $sql." where lower(i.title) like '%".str_replace("'", "''", $search)."%'";
            } else {
                $sql = $sql." where read_at is null";
            }

            $sql = $sql." order by published_at limit 1000";

        }

        $rows = [];
        $rs = $db->query($sql);
        while($row = $rs->fetchArray(SQLITE3_ASSOC)) {
            array_push($rows, $row);
        }

        $result = array( "time" => time(), "rows" => $rows );

        echo json_encode($result);
        exit(0);
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


         .form-search {
             padding-top: 10px;
             padding-bottom: 0;
             margin: 0;
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
                            <li><a id="add-feed" href="#add-feed-box" data-toggle="modal">Add</a></li>
                            <li><a id="mark-all-as-read" href="#">Mark All As Read</a></li>
                            <li><a id="refresh" href="#">Refresh</a></li>
                            <li><a href="#about-box" data-toggle="modal">About</a></li>
                            <li><div class="form-search"><input type="text" id="search" class="input-medium search-query" placeholder="Search"></div></li>
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

        <div class="modal hide fade" id="add-feed-box">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <h3>Add A Feed</h3>
            </div>
            <div class="modal-body">
                <form id="add-form" class="form-horizontal">
                    <fieldset>
                        <div class="control-group">
                            <label class="control-label">Name</label><div class="controls"><input type="string" id="feed-name"/></div>
                        </div>
                        <div class="control-group">
                            <label class="control-label">Url</label><div class="controls"><input type="string" id="feed-url"/></div>
                        </div>
                    </fieldset>
                </form>
            </div>
            <div class="modal-footer">
                <a href="#" data-dismiss="modal" class="btn">Close</a>
                <button id="add-feed-btn" class="btn btn-primary">Add Feed</button>
            </div>
        </div>

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
             $.ajax({method: "POST", data: {cmd: "read", item_id: event.target.id}}).then(function() {
                 $('#item-'+event.target.id).removeClass('unread-item').addClass('read-item');
             });

             return true;
         }

         function update_feed_display(e) {
             var $items = $('#items');
             if(e) {
                 $items.html("Searching...");
             }

             var search = $('#search').val();

             $.ajax({url: "?cmd=items", data: {search: search}}).then(function(result) {
                 var rows = result.rows;
                 var i, L, item;

                 if(e) {
                     $items.html("");
                 }

                 L = rows.length;
                 for(i=0; i < L; i++) {
                     item = rows[i];

                     if(document.getElementById('item-'+item.id) == null) {
                         var klass = item.read_at ? 'read-item' : 'unread-item';
                         $items.prepend($('<li id="item-'+item.id+'" class="row '+klass+'"><span class="feed-name span2">'+item.name+'</span><a href="'+item.url+'" id="'+item.id+'" tatget="_blank">'+item.title+'</a></li>'));
                         $('#'+item.id).click(item_clicked);
                     }
                 }

             });
         }

         function mark_all_as_read() {
             $.ajax({method: 'POST', data: {cmd: 'all_read'}}).then(function() {
                 $('li').removeClass('unread-item').addClass('read-item');
             });
         }

         function refresh_next_feed() {
             var feed = to_update.pop();

             if(feed) {
                 $.ajax({url: "?q="+encodeURIComponent(feed.url), cache: false}).then(function(data) {
                     var to_insert = [];
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
                             to_insert.push([title, url, published_at]);
                         }
                     });

                     return $.ajax({method: 'POST', data: {cmd: "add", feed_id: feed.id, items: JSON.stringify(to_insert)}});
                 }).always(function() {
                     window.setTimeout(refresh_next_feed, Math.floor(Math.random()*1000+100));
                 });
             }

             update_feed_display();
         }

         function refresh_feeds() {
             if(to_update.length == 0) {
                 $.ajax({url: "?cmd=feeds"}).then(function(result) {
                     var now = result.time;
                     var rows = result.rows;
                     if(rows == null || rows.length == 0) {
                         error('You have no feeds', 'Use the Configure option above to add some feeds');
                     } else {
                         var i;

                         for(i=0; i < rows.length; i++) {
                             var feed = rows[i];

                             if(feed.last_update + UPDATE_TIME < now) {
                                 to_update.unshift(feed);
                             }
                         }

                         refresh_next_feed();
                     }

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
             $('#search').on("change", update_feed_display);

             window.setInterval(refresh_feeds, 1000*60*5);

             $('#apply-config').click(function() {
                 $('#config-box').modal('hide');
                 if(xml_import_data) {
                     var xml = $.parseXML(xml_import_data);
                     var to_insert = [];
                     $(xml).find('outline').each(function(_i, e) {
                         if($(e).attr('type') == 'rss') {
                             to_insert.push([$(e).attr('title'), $(e).attr('xmlUrl')]);
                         }
                     })

                     $.ajax({method: 'POST', data: {cmd: 'add_feeds', feeds: JSON.stringify(to_insert)}});
                 }
             });

             $('#add-feed-btn').click(function() {
                 $('#add-feed-box').modal('hide');
                 var name = $('#feed-name').val();
                 var url = $('#feed-url').val();
                 if(name && url) {
                     var to_insert = [[name, url]];
                     $.ajax({method: 'POST', data: {cmd: 'add_feeds', feeds: JSON.stringify(to_insert)}});
                 }
             });

             $('#xml-import').change(function (evt) {
                 // check that the browser supports the file API
                 if (evt.target.files === undefined) {
                     alert("Your browser doesn't support the required APIs. Get a better browser!");
                     return undefined;
                 }

                 var f = evt.target.files[0];
                 var reader = new FileReader();

                 reader.onload = function (e) { xml_import_data = e.target.result };
                 reader.readAsText(f);
             });

             $.ajax({method: 'POST', data: {cmd: 'init'}}).then(refresh_feeds);
         });

         var favicon;

         function set_favicon(data) {
             if(!favicon) {
                 favicon = document.createElement('link');
                 favicon.rel = 'shortcut icon';
                 favicon.href = data;
                 document.getElementsByTagName('head')[0].appendChild(favicon);
             } else {
                 favicon.href = data;
             }
         }

         $(document).ajaxSend(function(event, request, settings) {
             set_favicon("data:image/gif;base64,R0lGODlhEAAQAPQAAP///yJ/I/j7+FKbU5TBlCeCKEKSQ9vq27PTszWKNYi6iHqyeufx56XLps3izWCjYWyqbQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh/hpDcmVhdGVkIHdpdGggYWpheGxvYWQuaW5mbwAh+QQJCgAAACwAAAAAEAAQAAAFUCAgjmRpnqUwFGwhKoRgqq2YFMaRGjWA8AbZiIBbjQQ8AmmFUJEQhQGJhaKOrCksgEla+KIkYvC6SJKQOISoNSYdeIk1ayA8ExTyeR3F749CACH5BAkKAAAALAAAAAAQABAAAAVoICCKR9KMaCoaxeCoqEAkRX3AwMHWxQIIjJSAZWgUEgzBwCBAEQpMwIDwY1FHgwJCtOW2UDWYIDyqNVVkUbYr6CK+o2eUMKgWrqKhj0FrEM8jQQALPFA3MAc8CQSAMA5ZBjgqDQmHIyEAIfkECQoAAAAsAAAAABAAEAAABWAgII4j85Ao2hRIKgrEUBQJLaSHMe8zgQo6Q8sxS7RIhILhBkgumCTZsXkACBC+0cwF2GoLLoFXREDcDlkAojBICRaFLDCOQtQKjmsQSubtDFU/NXcDBHwkaw1cKQ8MiyEAIfkECQoAAAAsAAAAABAAEAAABVIgII5kaZ6AIJQCMRTFQKiDQx4GrBfGa4uCnAEhQuRgPwCBtwK+kCNFgjh6QlFYgGO7baJ2CxIioSDpwqNggWCGDVVGphly3BkOpXDrKfNm/4AhACH5BAkKAAAALAAAAAAQABAAAAVgICCOZGmeqEAMRTEQwskYbV0Yx7kYSIzQhtgoBxCKBDQCIOcoLBimRiFhSABYU5gIgW01pLUBYkRItAYAqrlhYiwKjiWAcDMWY8QjsCf4DewiBzQ2N1AmKlgvgCiMjSQhACH5BAkKAAAALAAAAAAQABAAAAVfICCOZGmeqEgUxUAIpkA0AMKyxkEiSZEIsJqhYAg+boUFSTAkiBiNHks3sg1ILAfBiS10gyqCg0UaFBCkwy3RYKiIYMAC+RAxiQgYsJdAjw5DN2gILzEEZgVcKYuMJiEAOwAAAAAAAAAAAA==");
         });

         $(document).ajaxComplete(function(event, request, settings) {
             set_favicon("data:image/gif;base64,R0lGODlhEAAQAKUAAAQCBISChMTCxFxeXKSipOTm5BweHGxubJSSlPT29BQWFMzOzLy6vAwKDIyKjGRmZOzu7CwqLHR2dMzKzKyurJyanPz+/AQGBISGhMTGxGRiZKSmpOzq7CQiJHRydJSWlPz6/BwaHNTS1Ly+vAwODIyOjGxqbPTy9DQyNHx6fP///wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAACoALAAAAAAQABAAAAaVQJVwSCwaQaPKRwAyDgkGQKdzUVSch0sKIuRgGo8i4SIocIiCy1UICgVUKUCEMnQomqoR4FxYYEgSQhAXDEIVHUISFyYZJARCESVCH4hCExEDCB1NkUJpZxsRCwALFyKDhSogChgqGR4WBwsmCyUKCUMVZEYTDQhFJhcOXConJQ0DFkYVChcREVUIeEcMJQgMuE7aRUEAOw==");
         });
        </script>
    </body>
</html>
