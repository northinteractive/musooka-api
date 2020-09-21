<?php

function replace_urls($string, $rel = 'nofollow'){
    $host = "([a-z\d][-a-z\d]*[a-z\d]\.)+[a-z][-a-z\d]*[a-z]";
    $port = "(:\d{1,})?";
    $path = "(\/[^?<>\#\"\s]+)?";
    $query = "(\?[^<>\#\"\s]+)?";
    return preg_replace("#((ht|f)tps?:\/\/{$host}{$port}{$path}{$query})#i", "<a href=\"$1\" rel=\"{$rel}\">$1</a>", $string);
}

define ('ROOT',"/ebs1/www/html/");
define ("BASE","http://".$_SERVER['SERVER_NAME']."/");

if(isset($_POST['data'])) {

    $artist = json_decode($_POST['data']);
    $view =  "<div style='display:block;padding:20px;'>";

    $view .= "<div style='display:block;max-width:200px;min-width:200px;height:auto;float:left;overflow:hidden;'>";
    $view .= "<div style='display:block;width:200px;height:auto;text-align:left;'><img src='" . $artist->data->artist->artist_avatar . "' alt='' /></div>";

    // $view .= "<a href='http://twitter.com/home?status=Check out this artist on Muzooka http://muzooka.com/#!/".$artist->data->artist->artist_vanity."' title='Click to share this post on Twitter'>Share on Twitter</a>";

    $view .= "<div style='display:block;float:left;width:100px;margin-top:10px;' class='share-soon'><img src='https://s3.amazonaws.com/muzooka-website/images/social_share.png' alt='social sharing' /></div>";

    // $view .= "<div style='display:block;float:left;width:100px;margin-top:10px;'><a href='https://twitter.com/share' class='twitter-share-button' data-count='horizontal'>Tweet</a><script type='text/javascript' src='//platform.twitter.com/widgets.js'></script></div>";
    // $view .= '<div id="fb-root"></div><div style="display:block;float:left;width:100px;margin-top:10px;"><div class="fb-like" data-send="false" data-layout="button_count" data-width="450" data-show-faces="true" data-action="like"></div></div><div class="clear"></div>';

    // $view .= '<div style="display:block;float:left;width:100px;"><div class="fb-like" data-send="false" data-layout="box_count" data-width="100" data-show-faces="false" data-action="recommend" data-font="arial" style="float:left;"></div></div><div class="clear"></div>';

    $view .= "</div>";

    $view .= "<div style='display:block;float:left;padding-left:20px;max-width:320px;'>";

    $view .= "<h1 class='artist-title'>" . $artist->data->artist->artist_name . "</h1>";

    $view .= "<div class='rank-box'><h2>" . $artist->data->artist->follow_count . "</h2><span>followers</span></div>";
    if(!$artist->data->artist->producer) {
        $view .= "<div class='rank-box'><h2>" . $artist->data->artist->vote_count . "</h2><span>votes</span></div>";
    }
    $view .= "<div class='rank-box'><h2>" . $artist->data->artist->update_count . "</h2><span>updates</span></div><div class='clear'></div>";

    $bio = replace_urls($artist->data->artist->artist_bio);

    $view .= "<p class='artist-bio'>" . $bio . "</p>";

    $view .= "</div>";
    $view .= "<div class='clear'></div>";

    // Check for Producer
    if(!$artist->data->artist->producer) {
        $view .= "<ul class='ios-list'><li style=''><span>All Songs</span><div class='clear'></div></li>";

        $n=count($artist->data->songs);

        for($i=0;$i<$n;$i++) {
            $view .= "<li>";
            $view .= '<input type="hidden" value="'.urlencode($artist->data->songs[$i]->song_uri).'" name="uri" />';
            $view .= '<input type="hidden" name="song_id" value="'.$artist->data->songs[$i]->song_id.'" />';

            $view .= "<a href='#!/song/".$artist->data->artist->artist_id."/".$artist->data->songs[$i]->song_id."/' class='play_now song-element'>";
            $view .= "<input name='uri' type='hidden' value='".urlencode($artist->data->songs[$i]->song_uri)."' />";
            $view .= "<input name='song_title' type='hidden' value='".$artist->data->songs[$i]->song_title."' />";
            $view .= "<input name='artist_id' type='hidden' value='".$artist->data->songs[$i]->artist_id."' />";
            $view .= "<input name='artist_name' type='hidden' value='".$artist->data->artist->artist_name."' />";
            $view .= "<input name='artist_avatar' type='hidden' value='".$artist->data->artist->artist_avatar."' />";
            $view .= "<input name='song_id' type='hidden' value='".$artist->data->songs[$i]->song_id."' />";
            $view .= "<input name='artist_vanity' type='hidden' value='".$artist->data->artist->artist_vanity."' />";
            $view .= "</a>";
            $view .= "<input type='hidden' name='share-link' class='share-link' value='http://www.muzooka.com/#!/".$artist->data->artist->artist_vanity."/".$artist->data->songs[$i]->song_id."' />";
    
            $view .= "<a href='#' class='share' style='padding:2px;margin-top:0px;background:#db490b;'><img src='https://s3.amazonaws.com/muzooka-website/images/share.arrow.w.png' alt='' style='margin-top:1px;width:14px;height:10px;' /></a>";
            $view .= "<a href='#' class='add-to-playlist right' title='add this song to a playlist'>+</a>";
            
            if ($artist->data->songs[$i]->user_vote != '') {
				$view .= '<a href="#" class="vote-up right" title="you have voted for this song"><img src="https://s3.amazonaws.com/muzooka-website/images/voted.button.png" alt="vote for this song" /></a>';
			} else {
				$view .= '<a href="#" class="vote-up right" title="vote for this song"><img src="https://s3.amazonaws.com/muzooka-website/images/vote.button.png" alt="vote for this song" /></a>';
			}
            
            $view .= "<span>".$artist->data->songs[$i]->song_title."</span>";

            $view .= "<div class='clear'></div></li>";
        }
        $view .= "</ul>";
    }

    $view .= "</div>";
    
    
    
    $view .= "<ul class='updates'></ul>"; // <a href='#!/home/' class='more load_more_updates'>more</a>

    echo $view;

} else {
    echo "No artist found";
}