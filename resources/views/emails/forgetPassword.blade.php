@component('mail::message')
    Your New Password Generated<br>
    Your new Password is <b>{{ $password }}</b>.<br>
    Please change the password after login.<br>
    Thanks,<br>
    {{ config('app.name') }}
@endcomponent
