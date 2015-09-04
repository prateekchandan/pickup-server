Hey, we want to verify that you are indeed "{{$name}}".&nbsp; Verifying this address will let you receive notifications and password resets from Pickup.&nbsp; If you wish to continue, please follow the link below:<br>
<br>
<a href="{{URL::Route('verify')}}/{{$encryption}}" target="_blank">{{URL::Route('verify')}}/{{$encryption}}</a><br>
<br>
If you're not {{$name}} or didn't request verification, you can ignore this email.