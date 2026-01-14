<div class="space-y-4">
    <div>
        <div class="text-sm text-gray-400">Username</div>
        <div class="font-mono text-sm">
            {{ $properties['username'] ?? 'N/A' }}
        </div>
    </div>

    <div>
        <div class="text-sm text-gray-400">Password</div>
        <div class="flex items-center gap-2">
            <input
                id="keyhelp-password"
                type="password"
                class="input input-sm w-full"
                value="{{ $properties['password'] ?? '' }}"
                readonly
            >
            <button
                type="button"
                class="btn btn-sm"
                onclick="(function(){
                    const input = document.getElementById('keyhelp-password');
                    input.type = input.type === 'password' ? 'text' : 'password';
                })()"
            >
                Show / Hide
            </button>
        </div>
    </div>

    @if(session('keyhelp_password_reset'))
        <div class="mt-2 text-sm text-green-400">
            New password:
            <span class="font-mono">
                {{ session('keyhelp_password_reset') }}
            </span>
        </div>
    @endif
</div>
