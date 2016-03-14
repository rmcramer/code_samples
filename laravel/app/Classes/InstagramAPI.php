<?php namespace App;

//
// Instagram API GET
//

class InstagramAPI
{

    /**
     * Use the Instagram API to grab Instagram posts for the given site's Instagram feed.
     *
     * @param  Site $site
     *
     * @return array|null
     */
    public static function getPosts($site,$num_of_posts = 50)
    {
        $method = '/media/recent';
        $results = [];
        $IG_CLIENT_ID = null;
        $IG_USER_ID = null;

        $IG_API_URL = getenv('IG_API_URL');

        if ($IG_API_URL && $site && $site->cfg_array['IG_CLIENT_ID']->typed_value && $site->cfg_array['IG_USER_ID']->typed_value)
        {
            $url = $IG_API_URL . '/' . $site->cfg_array['IG_USER_ID']->typed_value . $method . '?client_id=' . $site->cfg_array['IG_CLIENT_ID']->typed_value . '&count=' . $num_of_posts . '&MIN_TIMESTAMP=1400000000';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
            $results = json_decode(curl_exec($ch),true);
            curl_close($ch);
        }

        return $results;
    }
}