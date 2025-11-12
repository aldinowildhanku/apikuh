<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SmtpController extends Controller
{
    public function send(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email'           => 'required|email',
            'password'        => 'required|string',
            'targetemail'     => 'required|email',
            'hostnameserver'  => 'required|string',
            'port'            => 'required|integer',
            'enkripsi'        => 'nullable|string|in:ssl,tls,null',
            'subject'         => 'required|string',
            'message'         => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'failed',
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {

            $encryption = $request->enkripsi;
            if ($encryption === 'null' || $encryption === null) {
                $encryption = null;
            }

            $useEncryption = $encryption === 'ssl' || $encryption === 'tls';


            $transport = new EsmtpTransport(
                $request->hostnameserver,
                $request->port,
                $useEncryption
            );
            $transport->setUsername($request->email);
            $transport->setPassword($request->password);

            $mailer = new Mailer($transport);


            $email = (new Email())
                ->from($request->email)
                ->to($request->targetemail)
                ->subject($request->subject)
                ->text($request->message);


            $mailer->send($email);

            return response()->json([
                'status' => 'ok',
                'message' => 'successfully send email'
            ], 200);

        } catch (TransportExceptionInterface $e) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed sending email'
            ], 500);
        }
    }
}
