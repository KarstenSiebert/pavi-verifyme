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
        $ageCheck = 16;

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
        
        $dataArray = [
            "vurl" => "https://verify.siehog.com/api/verification/tester",
            "mina" => $ageCheck,
            "user" => $user
        ];
        
        $data = json_encode($dataArray);
                            
        $renderer = new ImageRenderer(new RendererStyle(420, 1, null, null, $fill), new ImagickImageBackEnd());
        
        $qrcode = 'data:image/png;base64,'.base64_encode((new Writer($renderer))->writeString($data));
                                    
        return $qrcode;
    }
    
    // The vurl accepts the response from the validator, the validator will post the result after verification

    public function tester(Request $request): JsonResponse
    {
        $userId = $request->input('userid');
        $issuer = $request->input('issuer');
        $status = $request->input('status');

        file_put_contents('/tmp/' . trim($userId), $issuer . ':' . $status);

        return response()->json([]);
    }

    // The demo uses polling to check, if we received a message from the validator

    public function checker(Request $request): JsonResponse
    {
        $file = $request->input('user') ? '/tmp/' . trim($request->input('user')) : "";

        if (empty($file) || !file_exists($file)) {
            return response()->json(['ready' => false, 'result' => 'nofile']);
        }

        $content = file_get_contents($file);

        // true oder false

        $ageCheck = (int) $request->input('age', 0);

        if (preg_match('/^[^:]+:(.+)$/', trim($content), $matches)) {

            unlink($file);

            $display = trim($matches[1]);
            
            $result = 'false';

            if (str_contains($display, "true")) {
                $result = 'true';
                $request->session()?->put('age_verified', true);                
            }

            return response()->json(['ready' => true, 'result' => $result]);
        }

        unlink($file);

        return response()->json(['ready' => true, 'result' => 'false']);
    }

}
