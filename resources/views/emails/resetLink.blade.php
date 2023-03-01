@component('mail::message')
    <h1>Hello {{ $user->name }}</h1>
    <p>A password reset request has been created.</p>
    <a href={{ $url }}><button type="button" class="btn btn-success">Click Here to change password</button></a>
    <br>
    Thanks,<br>
    {{ config('app.name') }}
    <br><br>
    <a type="button" class="btn btn-success" href={{ $url }}>{{ $url }}</a>
@endcomponent
