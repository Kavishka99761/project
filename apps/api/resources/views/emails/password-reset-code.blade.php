<x-mail::message>
# Reset your password

Hello {{ $user->name }},

Someone asked to reset the password for the AcadeAlert account **{{ $user->email }}**.
Use this code to continue:

<x-mail::panel>
# {{ trim(chunk_split($code, 3, ' ')) }}
</x-mail::panel>

The code expires in {{ $minutes }} minutes and can only be used once.

<x-mail::button :url="$link">
Reset my password
</x-mail::button>

If you did not ask for this, you can ignore this email — your password stays as it is,
and nobody can change it without the code above.

Thanks,<br>
The AcadeAlert team

<x-mail::subcopy>
Trouble with the button? Open this address in your browser: {{ $link }}
</x-mail::subcopy>
</x-mail::message>
