<?php

class SuiteCRMAPI
{
    $access_token = null;
    $base_url = '';
    $client_id = '';
    $client_secret = '';

    public function setBaseUrl($url = null) {
      if (isset($url) && $url != ''){
        $base_url = $url;
      }
    }

    public function setAccessToken($token = null) {
      if (isset($token) && $token != ''){
        $access_token = $token;
      }
    }

    public function setClientId($id = null) {
      if (isset($id) && $id != ''){
        $client_id = $id;
      }
    }

    public function setClientSecret($secret = null) {
      if (isset($secret) && $secret != ''){
        $client_secret = $secret;
      }
    }

    public function login(){

      $login_params = json_encode(array(
         'grant_type' => 'client_credentials',
         'client_id' => $client_id,
         'client_secret' => $client_secret,
         'scope' => 'standard:create standard:read standard:update standard:delete standard:delete standard:relationship:create standard:relationship:read standard:relationship:update standard:relationship:delete'
      ));

  		$result = $this->apiClient('/api/oauth/access_token', $login_params, 'POST');

  		if (isset($result->access_token)) {
          setAccessToken($result->access_token);
          return $result->access_token;
      }

      return false;
    }

    public function apiClient($url = null, $data = array(), $request_type = 'GET') {
        $ch = curl_init();
        $header = array(
           'Content-type: application/vnd.api+json',
           'Accept: application/vnd.api+json',
        );

        $url = $base_url.$url;
        curl_setopt($ch, CURLOPT_URL, url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request_type);
        if(!empty($data))
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $output = curl_exec($ch);
        $output = json_decode($output);
        // return the result

        return $output;
    }
}
?>
