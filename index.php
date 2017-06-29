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
             db.transaction(function (tx) {
                 tx.executeSql('update items set read_at=? where id=?', [Date.now(), event.target.id]);
             });

             $('#item-'+event.target.id).removeClass('unread-item').addClass('read-item');

             return true;
         }

         function update_feed_display(e) {
             if(e) {
                 items.html("Searching...");
             }

             var search = $('#search').val();
             var sql = "select name, i.* from items i join feeds f on f.id=i.feed_id"

             if(search) {
                 sql = sql + " where lower(i.title) like '%" + search.replace("'", "''") + "%'";
             } else {
                 sql = sql + " where read_at is null";
             }

             sql = sql + " order by published_at limit 1000";

             db.transaction(function (tx) {
                 tx.executeSql(sql, [], function(tx, results) {
                     var i, L, item;
                     var items = $('#items');

                     if(e) {
                         items.html("");
                     }

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
             $('#search').on("change", update_feed_display);

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

             $('#add-feed-btn').click(function() {
                 $('#add-feed-box').modal('hide');
                 var name = $('#feed-name').val();
                 var url = $('#feed-url').val();
                 if(name && url) {
                     db.transaction(function (tx) {
                         tx.executeSql('insert into feeds (name, url, last_update) values (?, ?, 0)', [name, url]);
                     });
                     $('#feed-name').val('');
                     $('#feed-url').val('');
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
