<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use BaconQrCode\Renderer\Color\Rgb;
use Illuminate\Support\Facades\Hash;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class VerificationController extends Controller
{
    function index(Request $request): Response
    {                       
        $ageCheck = 15;

        $vurl = env('PROVIDER_UUID');

        $nonce = Str::random(16);
        
        $codeName = Hash::make(base64_encode($vurl.$ageCheck.$nonce));

        $qrcImage = $this->qrImage($codeName, $ageCheck, $vurl, $nonce);
    
        return Inertia::render('Verify', 
                    [
                        'ageCheck' => $ageCheck,
                        'qrcImage' => $qrcImage,
                        'codeName' => $codeName
                    ]);
    }

    // Whether login or no access in this demo, make sure to visit the middleware handler.
    // Links (e.g. /login) cannot be shortcut, the handler will always redirect, if the 
    // age_verified variable is not set.

    function noaccess(Request $request): Response
    {        
        return Inertia::render('NoAccess', [
        ]);
    }

    public function qrImage(string $code, string $ageCheck, string $vurl, string $nonce): string
    {        
        $foregroundColor = new Rgb(50, 50, 50);

        $backgroundColor = new Rgb(255, 255, 255);

        $fill = Fill::uniformColor($foregroundColor, $backgroundColor);

        $dataArray = [
            "vurl"    => $vurl,
            "min_age" => $ageCheck,            
            "code"    => $code,
            "nonce"   => $nonce
        ];

        $data = json_encode($dataArray);
                            
        $renderer = new ImageRenderer(new RendererStyle(420, 1, null, null, $fill), new ImagickImageBackEnd());
        
        $qrcode = 'data:image/png;base64,'.base64_encode((new Writer($renderer))->writeString($data));
                                    
        return $qrcode;
    }

    public function tester(Request $request): JsonResponse
    {
        $issuer = $request->input('issuer');
        $creds  = $request->input('creds');
        $code   = $request->input('code');
        
        file_put_contents('/tmp/' . trim($code), $issuer . ':' . $creds);

        return response()->json([]);
    }

    public function checker(Request $request): JsonResponse
    {
        $file = $request->input('code') ? '/tmp/' . trim($request->input('code')) : "";

        if (empty($file) || !file_exists($file)) {
            return response()->json(['ready' => false, 'result' => 'nofile']);
        }

        $content = file_get_contents($file);

        // true oder false

        if (preg_match('/^[^:]+:(.+)$/', trim($content), $matches)) {

            unlink($file);

            $datas = base64_decode(trim($matches[1]));

            try {            
                $creds = json_decode($datas, true);

                if (array_key_exists('proof', $creds) && array_key_exists('field', $creds)) {
                    $proof = $creds['proof'];

                    // proof->publickey, proof->signature, add check signature here, content = creds['field']

                    $datas = base64_decode($creds['field']);

                    $field = json_decode($datas, true);
                
                    if (array_key_exists('show', $field)) {            
                        $display = $field['show'];
            
                        $result = 'false';

                        if (str_contains($display, "true")) {
                            $result = 'true';
                            $request->session()?->put('age_verified', true);                
                        }
                    }
                }

            } catch (Exception $e) {

            }

            return response()->json(['ready' => true, 'result' => $result]);
        }

        unlink($file);

        return response()->json(['ready' => true, 'result' => 'false']);
    }

}
