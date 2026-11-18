<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Message {{ '@'.$username }} anonymously · Psst</title>
<meta name="robots" content="noindex, nofollow">
<meta http-equiv="refresh" content="0;url=/{{ $username }}">
<link rel="canonical" href="{{ $appUrl }}/{{ $username }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Psst">
<meta property="og:title" content="Message {{ '@'.$username }} anonymously · Psst">
<meta property="og:description" content="Send {{ '@'.$username }} an anonymous message. They will never know who it was.">
<meta property="og:image" content="{{ $appUrl }}/og.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="{{ $appUrl }}/{{ $username }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Message {{ '@'.$username }} anonymously · Psst">
<meta name="twitter:description" content="Send {{ '@'.$username }} an anonymous message. They will never know who it was.">
<meta name="twitter:image" content="{{ $appUrl }}/og.png">
</head>
<body>
<p><a href="/{{ $username }}">Message {{ '@'.$username }} anonymously on Psst</a></p>
</body>
</html>
