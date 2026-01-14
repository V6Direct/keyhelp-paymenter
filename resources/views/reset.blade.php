<div class="space-y-4">
    <p class="text-sm text-gray-300">
        Click the button below to generate a new password for your KeyHelp account.
        After the reset, the new password will appear in the Login Data tab.
    </p>

    <button
        type="button"
        class="btn btn-primary btn-sm"
        wire:click="goto('clientResetPassword')"
    >
        Reset Password
    </button>
</div>
