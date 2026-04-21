<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

class VerificationController extends Controller
{
    function index(Request $request): Response
    {                       
        $ageCheck = 14;

        $userName = Str::uuid();

        $qrcImage = $this->qrImage($userName, $ageCheck);
    
        return Inertia::render('Verify', 
                    [
                        'ageCheck' => $ageCheck,
                        'qrcImage' => $qrcImage,
                        'userName' => $userName
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

    public function qrImage(string $user, string $ageCheck): string
    {        
        $foregroundColor = new Rgb(50, 50, 50);

        $backgroundColor = new Rgb(255, 255, 255);

        $fill = Fill::uniformColor($foregroundColor, $backgroundColor);

        $nonce = Str::random(32);
        
        $dataArray = [
            "vurl"  => env('PROVIDER_UUID'),
            "mina"  => $ageCheck,
            "user"  => $user,
            "nonce" => $nonce
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
        $user   = $request->input('user');
        
        file_put_contents('/tmp/' . trim($user), $issuer . ':' . $creds);

        return response()->json([]);
    }

    public function checker(Request $request): JsonResponse
    {
        $file = $request->input('user') ? '/tmp/' . trim($request->input('user')) : "";

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
