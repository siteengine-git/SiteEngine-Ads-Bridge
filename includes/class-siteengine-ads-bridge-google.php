<?php
defined('ABSPATH') || exit;
/** Shared existing JWT signer. No transport or positive eligibility cache. */
final class SiteEngine_Ads_Bridge_Google {
        public static function token( $creds ) {
                $out = array( 'ok' => false, 'token' => '', 'error' => '' );
                if(!is_array($creds)||!is_string($creds['client_email']??null)||!is_string($creds['private_key']??null)||$creds['client_email']===''||$creds['private_key']==='')return ['ok'=>false,'token'=>'','error'=>'Service-account identity/key is missing or malformed; contents omitted.'];

                if ( ! function_exists( 'openssl_sign' ) ) {
                        $out['error'] = 'openssl extension unavailable; cannot sign the service-account JWT.';
                        return $out;
                }

                $token_uri = 'https://oauth2.googleapis.com/token';
                if(isset($creds['token_uri']) && $creds['token_uri']!==$token_uri)return ['ok'=>false,'token'=>'','error'=>'Unsupported OAuth endpoint; expected Google OAuth.'];
                $scope     = 'https://www.googleapis.com/auth/spreadsheets';
                $now       = time();

                $b64url = function ( $data ) {
                        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
                };

                $header_seg = $b64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
                $claim_seg  = $b64url(
                        wp_json_encode(
                                array(
                                        'iss'   => (string) $creds['client_email'],
                                        'scope' => $scope,
                                        'aud'   => $token_uri,
                                        'iat'   => $now,
                                        'exp'   => $now + 3600,
                                )
                        )
                );

                $signing_input = $header_seg . '.' . $claim_seg;
                $signature     = '';
                $signed        = @openssl_sign( $signing_input, $signature, (string) $creds['private_key'], OPENSSL_ALGO_SHA256 );
                if ( ! $signed ) {
                        $out['error'] = 'openssl_sign failed (check the private_key in the service-account JSON).';
                        return $out;
                }
                $jwt = $signing_input . '.' . $b64url( $signature );

                $response = wp_remote_post(
                        $token_uri,
                        array(
                                'timeout' => 10,
                                'redirection'=>0, 'limit_response_size'=>65536,
                                'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
                                'body'    => array(
                                        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                                        'assertion'  => $jwt,
                                ),
                        )
                );

                if ( is_wp_error( $response ) ) {
                        $out['error'] = 'token request transport error: ' . 'request failed (credentials and remote response omitted)';
                        return $out;
                }

                $code = (int) wp_remote_retrieve_response_code( $response );
                $body = (string) wp_remote_retrieve_body( $response );
                $json = json_decode( $body, true );

                if ( 200 !== $code || ! is_array( $json ) || !is_string($json['access_token']??null) || strlen($json['access_token'])<1 || strlen($json['access_token'])>8192 ) {
                        $out['error'] = sprintf('Google authentication failed (HTTP %d); check credential validity and server clock. Secret response omitted.', $code);
                        return $out;
                }

                $out['ok']    = true;
                $out['token'] = (string) $json['access_token'];
                return $out;
        }

}
