@php
    /**
     * Kumpulkan pesan flash dari server untuk diteruskan ke toast JS.
     * Dipakai di layout mana pun dengan menyertakan variabel $toasts.
     */
    $flashToasts = [];

    foreach (['success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $type) {
        if (session()->has($key)) {
            $flashToasts[] = ['message' => session($key), 'type' => $type];
        }
    }

    // Error validasi pertama juga ditampilkan sebagai toast.
    if ($errors->any()) {
        $flashToasts[] = ['message' => $errors->first(), 'type' => 'error'];
    }
@endphp

<script type="application/json" id="tik-flash">@json($flashToasts)</script>
