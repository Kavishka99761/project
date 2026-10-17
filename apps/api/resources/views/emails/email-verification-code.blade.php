<x-mail::message>
# Welcome to AcadeAlert, {{ Str::before($user->name, ' ') }}!

One last step: confirm that **{{ $user->email }}** is your address.
Enter this code in the app:

<x-mail::panel>
# {{ trim(chunk_split($code, 3, ' ')) }}
</x-mail::panel>

<x-mail::button :url="$link">
Confirm my email
</x-mail::button>

Once confirmed you will get deadline reminders, weekly study summaries and
password recovery at this address.

If you did not create an AcadeAlert account, simply ignore this email.

Thanks,<br>
The AcadeAlert team

<x-mail::subcopy>
Trouble with the button? Open this address in your browser: {{ $link }}
</x-mail::subcopy>
</x-mail::message>
