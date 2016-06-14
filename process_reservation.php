<?php
ini_set('max_execution_time', 300); // 5 minutes
$expected = ['name','telephone','email','address','nationality','doc_num','travel_motive','extra_beds','Check-in','Check-out','room_type','adults','msg'];
$required = ['name','telephone','email','address','nationality','doc_num','travel_motive','extra_beds','Check-in','Check-out'];

// check $_POST array
foreach ($_POST as $key => $value) {
    if (in_array($key, $expected)) {
        if (!is_array($value)) {
            $value = trim($value);
        }
        if (empty($value) && in_array($key, $required)) {
            $$key = '';
            $missing[] = $key;
        } else {
            $$key = $value;
        }
    }
}

// check email address
if (!in_array($email, $missing)) {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $errors['email'] = 'Please use a valid email address';
    }
}


// process only if there are no errors or missing fields
if (!$errors && !$missing) {
    require_once 'config.php';

    // set up replacements for decorator plugin
    $replacements = [
        'pensaopaulo@hotmail.com'  =>
            ['#subject#' => 'Reservation Request - Pensão Paulo',
                '#greeting#' => "$name, has submitted a booking request"],
		$email =>
            ['#subject#' => 'Reservation Request Pensão Paulo',
                '#greeting#' => "Thanks $name, your booking request was received!"]	
   ];

    try {
        // create a transport
        $transport = Swift_SmtpTransport::newInstance($smtp_server, 587, 'tls')
            ->setUsername($username)
            ->setPassword($password);
        $mailer = Swift_Mailer::newInstance($transport);

        // register the decorator and replacements
        $decorator = new Swift_Plugins_DecoratorPlugin($replacements);
        $mailer->registerPlugin($decorator);

        // initialize the message
        $message = Swift_Message::newInstance()
            ->setSubject('#subject#')
            ->setFrom($from);

            //embed image in email
            $image_logo = $message->embed(Swift_Image::fromPath('img/logo.png'));
            $image_ilha = $message->embed(Swift_Image::fromPath('img/brava.png'));
            $image_hotel = $message->embed(Swift_Image::fromPath('img/gallery/pensao_paulo_01.jpg'));
			$image_local = $message->embed(Swift_Image::fromPath('img/local2.png'));

        // create the first part of the HTML output
        $html = <<<EOT
<html lang="en">
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title>Pensão Paulo </title>
</head>
<body bgcolor="#EBEBEB" link="#B64926" vlink="#FFB03B">
<table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#EBEBEB">
<tr>
<td>
<table width="600" align="center" border="0" cellpadding="0" cellspacing="0" bgcolor="#FFFFFF">
<tr>
<td style="text-align:center; padding:2em;"><img src="$image_logo"></td>
</tr>
<tr>
<td style="padding-top: 0.5em">
<h1 style="font-family: 'Lucida Grande', 'Lucida Sans Unicode', Verdana, sans-serif; color: #0E618C; text-align:
center">Reservation Request - Pensão Paulo</h1>
</td>
</tr>
<tr>
<td style="padding-left:5em;padding-right:5em;">
<p>Pensão Paulo: Paulo Sena - Rua da cultura, Brava, CABO VERDE</p>
<p><b>Telefone:</b> (+238) 285 13 12</p>
</td>
</tr>
<tr>
<td style="padding-left:5em;padding-right:5em;">
<img src="$image_local" style="width:200px; height:110px;">
<img src="$image_hotel" style="width:200px; height:100px;">
</td>
</tr>
<tr>
<td style="padding-top: 0.5em">
<h3 style="font-family: 'Lucida Grande', 'Lucida Sans Unicode', Verdana, sans-serif; color: #0E618C;padding-left:4.3em;padding-right:5em;">Request Details</h3>
EOT;

        // initialize variable for plain text version
        $text = '';

        // add each form element to the HTML and plain text content
        foreach ($expected as $item) {
            if (isset($$item)) {
                $value = $$item;
                $label = ucwords(str_replace('_', ' ', $item));
                $html .= "<p style='padding-left:5em;padding-right:5em;'><b>$label:</b>";
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $html .= " $value</p>";
                $text .= "$label: $value\r\n";
            }
        }

        // complete the HTML content
        $html .= '</td></tr>';
        $html .="<tr>
                <td style='text-align:center'><img src='$image_ilha'></td>
                </tr>";

        $html .= "<footer style='text-align:center;padding-bottom:1em;'>eTourism Project by Prime Consulting &middot; <a>Termos</a> &middot; <a>Privacidade</a></footer></table></body></html>";

        // set the HTML body and add the plain text version
        $message->setBody($html, 'text/html')
            ->addPart($text, 'text/plain');

        // initialize variables to track the emails
        $sent = 0;
        $failures = [];

        // send the messages
        foreach ($replacements as $recipient => $values) {
            $message->setTo($recipient);
            $sent += $mailer->send($message, $failures);
        }

        // if the message have been sent, redirect to relevant page
        if ($sent == 2) {
            header('Location: reservas_sucess.html');
            exit;
        }

// handle failures
        $num_failed = count($failures);
        if ($num_failed == 2) {
            $f = 'both';
        } elseif (in_array($email, $failures)) {
            $f = 'email';
        } else {
            $f = 'reg';
        }

// IMPORTANT: log an error before redirecting

        header("Location: reservas.html");
        exit;
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}