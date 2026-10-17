<x-mail::message>
# Your password was changed

Hello {{ $user->name }},

The password for your AcadeAlert account **{{ $user->email }}** was changed on
{{ $when }}@if ($ip) from IP address {{ $ip }}@endif.

Every other device has been signed out, so you will need to sign in again on them.

**If this was not you**, reset your password immediately and the current one stops working:

<x-mail::button :url="rtrim(config('app.frontend_url'), '/').'/login'">
Go to AcadeAlert
</x-mail::button>

Thanks,<br>
The AcadeAlert team
</x-mail::message>
