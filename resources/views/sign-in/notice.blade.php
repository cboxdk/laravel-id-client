{{--
    The one page an app without a sign-in page still has: a sign-in that failed, or
    "you are signed out". No form, no script, nothing from another origin. Replace it
    with your own view through `cbox-id-client.sign_in.view`; it gets the same variables.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ $app }}</title>
    <style @if ($nonce) nonce="{{ $nonce }}" @endif>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; background: Canvas; color: CanvasText; }
        main { max-width: 26rem; }
        h1 { font-size: 1.25rem; line-height: 1.3; margin: 0 0 .5rem; }
        p { margin: 0 0 1.25rem; }
        nav { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; align-items: center; }
        a { color: inherit; }
        a.primary { padding: .5rem 1rem; border-radius: .5rem; background: CanvasText; color: Canvas; text-decoration: none; font-weight: 600; }
        small { display: block; margin-top: 1.5rem; opacity: .65; }
    </style>
</head>
<body>
<main>
    <h1>{{ $title }}</h1>
    <p @if ($kind === 'error') role="alert" @endif>{{ $message }}</p>
    <nav aria-label="{{ __('Sign-in') }}">
        @if ($signInUrl)
            <a class="primary" href="{{ $signInUrl }}">{{ $kind === 'error' ? __('Try again') : __('Sign in') }}</a>
        @endif
        @if ($otherAccountUrl)
            <a href="{{ $otherAccountUrl }}">{{ __('Use another account') }}</a>
        @endif
    </nav>
    @if ($code)
        <small>{{ __('Reference: :code', ['code' => $code]) }}</small>
    @endif
</main>
</body>
</html>
