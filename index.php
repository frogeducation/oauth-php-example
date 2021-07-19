<?php

error_reporting(E_ALL);
ini_set("display_errors", "on");
ini_set("display_startup_errors", "on");

use FrogOS\OAuth1Example\Client\Server\FrogOS;

require "vendor/autoload.php";

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . "/templates");
$twig = new \Twig\Environment($loader, [
    "cache" => false,
    "debug" => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

if (!file_exists(__DIR__ . "/config.ini")) {
    echo $twig->render("missing_config.html.twig");
    exit(1);
}

$config = parse_ini_file(__DIR__ . "/config.ini");

$server = new FrogOS(
    [
        "identifier" => $config["key"],
        "secret" => $config["secret"],
        "callback_uri" => "http://localhost:8080",
    ],
    $config["baseUrl"]
);

session_start();

// Step 4
if (isset($_GET["user"])) {
    // Check somebody hasn't manually entered this URL in,
    // by checking that we have the token credentials in
    // the session.
    if (!isset($_SESSION["token_credentials"])) {
        echo "No token credentials.";
        exit();
    }

    // Retrieve our token credentials. From here, it's play time!
    $tokenCredentials = unserialize($_SESSION["token_credentials"]);

    $user = $server->getUserDetails($tokenCredentials);

    $client = $server->createHttpClient();

    $url = sprintf("%s/api/fdp/1/auth/getauth", $config["baseUrl"]);
    $headers = $server->getHeaders($tokenCredentials, "GET", $url);
    $headers["X-AuthType"] = "oauth_1_0_a";

    $res = $client->get($url, ["headers" => $headers]);

    echo $twig->render("user.html.twig", [
        "configValues" => $config,
        "api2" => json_encode($user, JSON_PRETTY_PRINT),
        "fdp1" => json_encode(json_decode((string) $res->getBody(), true), JSON_PRETTY_PRINT),
    ]);
    exit();

    // Step 3
} elseif (isset($_GET["oauth_token"]) && isset($_GET["oauth_verifier"])) {
    // Retrieve the temporary credentials from step 2
    $temporaryCredentials = unserialize($_SESSION["temporary_credentials"]);

    // Third and final part to OAuth 1.0 authentication is to retrieve token
    // credentials (formally known as access tokens in earlier OAuth 1.0
    // specs).
    $tokenCredentials = $server->getTokenCredentials(
        $temporaryCredentials,
        $_GET["oauth_token"],
        $_GET["oauth_verifier"]
    );

    // Now, we'll store the token credentials and discard the temporary
    // ones - they're irrelevant at this stage.
    unset($_SESSION["temporary_credentials"]);
    $_SESSION["token_credentials"] = serialize($tokenCredentials);
    session_write_close();

    // Redirect to the user page
    header("Location: http://{$_SERVER["HTTP_HOST"]}/?user=user");
    exit();

    // Step 2.5 - denied request to authorize client
} elseif (isset($_GET["denied"])) {
    echo $twig->render("denied.html.twig");
    exit();

    // Step 2
} elseif (isset($_GET["login"])) {
    // First part of OAuth 1.0 authentication is retrieving temporary credentials.
    // These identify you as a client to the server.
    $temporaryCredentials = $server->getTemporaryCredentials();

    // Store the credentials in the session.
    $_SESSION["temporary_credentials"] = serialize($temporaryCredentials);
    session_write_close();

    // Second part of OAuth 1.0 authentication is to redirect the
    // resource owner to the login screen on the server.
    $server->authorize($temporaryCredentials);

    // Step 1
} else {
    $validBaseUrl = $config["baseUrl"] !== "https://frogos-one.local.frogdev.asia";
    $validKey = $config["key"] !== "9bobi5r5ku0wws04osogwkcws8n8eilj";
    $validSecret = $config["secret"] !== "dr27v6aduigww4os4o4wkwssw55yac7gcll444so";
    echo $twig->render("index.html.twig", [
        "configValues" => $config,
        "validConfig" => $validBaseUrl && $validKey && $validSecret,
        "validBaseUrl" => $validBaseUrl,
        "validKey" => $validKey,
        "validSecret" => $validSecret,
    ]);
    exit();
}
