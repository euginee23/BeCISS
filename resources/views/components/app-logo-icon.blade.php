{{-- BeCISS Logo Icon: Stylized community/barangay symbol --}}
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" {{ $attributes }}>
    {{-- House/Community symbol with connected people --}}
    <path 
        fill="currentColor" 
        fill-rule="evenodd" 
        clip-rule="evenodd"
        d="M20 2L4 14v22a2 2 0 002 2h28a2 2 0 002-2V14L20 2zm0 4.5L32 16v18H8V16l12-9.5z"
    />
    {{-- Center community circle --}}
    <circle fill="currentColor" cx="20" cy="24" r="6" />
    {{-- Connected nodes representing residents --}}
    <circle fill="currentColor" cx="12" cy="20" r="2.5" />
    <circle fill="currentColor" cx="28" cy="20" r="2.5" />
    <circle fill="currentColor" cx="20" cy="32" r="2.5" />
    {{-- Connection lines --}}
    <path 
        fill="currentColor" 
        d="M14.5 21.5L17 23M23 23L25.5 21.5M20 27v3"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
    />
</svg>
