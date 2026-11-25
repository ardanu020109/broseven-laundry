<script setup>
import { useId } from 'vue';

const uid = useId();

/** Gelombang air: periode 48 px, sehingga animasi geser 48 px berulang mulus. */
function wave(startX, y, amplitude, segments) {
    let path = `M ${startX} ${y} q 12 ${-amplitude} 24 0`;

    for (let index = 1; index < segments; index++) path += ' t 24 0';

    return `${path} V 330 H ${startX} Z`;
}

const waveBack = wave(90, 250, 14, 12);
const waveFront = wave(66, 262, 12, 14);
</script>

<template>
    <svg
        class="art"
        viewBox="0 0 460 440"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="Ilustrasi mesin cuci dengan cucian bersih"
    >
        <defs>
            <clipPath :id="`${uid}-door`">
                <circle cx="230" cy="244" r="64" />
            </clipPath>
        </defs>

        <circle class="blob blob--a" cx="230" cy="215" r="190" />
        <circle class="blob blob--b" cx="352" cy="108" r="64" />
        <circle class="blob blob--c" cx="96" cy="338" r="52" />

        <ellipse class="ground" cx="230" cy="368" rx="140" ry="12" />

        <g class="machine">
            <rect class="body" x="120" y="70" width="220" height="288" rx="28" />
            <path class="panel" d="M120 128 V98 a28 28 0 0 1 28-28 H312 a28 28 0 0 1 28 28 V128 Z" />
            <line class="panel-line" x1="120" y1="128" x2="340" y2="128" />

            <circle class="knob" cx="158" cy="99" r="13" />
            <circle class="knob-dot" cx="158" cy="93" r="3" />
            <rect class="display" x="196" y="86" width="92" height="26" rx="13" />
            <circle class="led led--1" cx="216" cy="99" r="4" />
            <circle class="led led--2" cx="232" cy="99" r="4" />
            <circle class="led led--3" cx="248" cy="99" r="4" />
            <circle class="push" cx="314" cy="99" r="9" />

            <circle class="door-ring" cx="230" cy="244" r="86" />
            <circle class="glass" cx="230" cy="244" r="66" />

            <g :clip-path="`url(#${uid}-door)`">
                <g class="tumble">
                    <rect class="cloth cloth--a" x="206" y="218" width="30" height="22" rx="6" transform="rotate(-18 221 229)" />
                    <circle class="cloth cloth--b" cx="252" cy="262" r="13" />
                    <rect class="cloth cloth--c" x="196" y="262" width="26" height="18" rx="5" transform="rotate(24 209 271)" />
                    <circle class="cloth cloth--a" cx="258" cy="226" r="9" />
                </g>
                <path class="wave wave--back" :d="waveBack" />
                <path class="wave wave--front" :d="waveFront" />
            </g>

            <path class="shine" d="M186 222 a50 50 0 0 1 26-22" />
        </g>

        <g class="towels">
            <rect class="towel towel--a" x="340" y="336" width="90" height="20" rx="8" />
            <rect class="towel towel--b" x="346" y="318" width="78" height="20" rx="8" />
            <rect class="towel towel--c" x="352" y="300" width="66" height="20" rx="8" />
        </g>

        <circle class="bubble bubble--1" cx="368" cy="204" r="10" />
        <circle class="bubble bubble--2" cx="392" cy="166" r="6" />
        <circle class="bubble bubble--3" cx="354" cy="152" r="5" />
        <circle class="bubble bubble--4" cx="84" cy="214" r="8" />
        <circle class="bubble bubble--5" cx="62" cy="248" r="5" />

        <g transform="translate(396 92)">
            <path class="spark spark--1" d="M0 -9 L2.4 -2.4 L9 0 L2.4 2.4 L0 9 L-2.4 2.4 L-9 0 L-2.4 -2.4 Z" />
        </g>
        <g transform="translate(70 120)">
            <path class="spark spark--2" d="M0 -7 L1.9 -1.9 L7 0 L1.9 1.9 L0 7 L-1.9 1.9 L-7 0 L-1.9 -1.9 Z" />
        </g>

        <g class="note anim-float">
            <rect class="note-card" x="22" y="276" width="140" height="54" rx="16" />
            <circle class="note-dot" cx="48" cy="303" r="12" />
            <path class="note-check" d="M42 303 l4 4 l8 -9" />
            <text class="note-title" x="68" y="299">Cucian siap</text>
            <text class="note-sub" x="68" y="316">diambil</text>
        </g>
    </svg>
</template>

<style scoped>
.art { display: block; width: 100%; max-width: 520px; height: auto; margin-inline: auto; }

.blob--a { fill: var(--color-primary-soft); }
.blob--b { fill: var(--color-accent-soft); }
.blob--c { fill: var(--color-secondary-soft); }
.ground { fill: color-mix(in srgb, var(--color-text) 10%, transparent); }

.machine { filter: drop-shadow(0 18px 22px color-mix(in srgb, var(--color-text) 20%, transparent)); }
.body { fill: var(--color-surface); stroke: var(--color-border); stroke-width: 2; }
.panel { fill: var(--color-primary-soft); }
.panel-line { stroke: var(--color-border); stroke-width: 2; }
.knob { fill: var(--color-surface); stroke: var(--color-secondary); stroke-width: 3; }
.knob-dot { fill: var(--color-secondary); }
.display { fill: var(--color-surface); stroke: var(--color-border); stroke-width: 2; }
.led--1 { fill: var(--color-accent); }
.led--2 { fill: var(--color-primary); }
.led--3 { fill: var(--color-secondary); }
.push { fill: var(--color-accent); stroke: var(--color-surface); stroke-width: 3; }

.door-ring { fill: var(--color-secondary-soft); stroke: var(--color-secondary); stroke-width: 6; }
.glass { fill: var(--color-surface); stroke: var(--color-border); stroke-width: 2; }
.wave--back { fill: var(--color-accent); opacity: 0.75; animation: wave-slide 4.5s linear infinite; }
.wave--front { fill: var(--color-primary); opacity: 0.9; animation: wave-slide 3s linear infinite; }
.shine { fill: none; stroke: #fff; stroke-width: 4; stroke-linecap: round; opacity: 0.8; }

.tumble { transform-origin: 230px 244px; animation: tumble 9s linear infinite; }
.cloth--a { fill: var(--color-secondary); }
.cloth--b { fill: var(--color-accent); }
.cloth--c { fill: var(--color-primary-strong); opacity: 0.6; }

.towel--a { fill: var(--color-accent); }
.towel--b { fill: var(--color-primary); }
.towel--c { fill: var(--color-secondary); }

.bubble { fill: color-mix(in srgb, var(--color-surface) 80%, transparent); stroke: var(--color-primary-strong); stroke-width: 2; opacity: 0; animation: bubble-rise 6s ease-in infinite; }
.bubble--2 { animation-delay: 1.2s; animation-duration: 5s; }
.bubble--3 { animation-delay: 2.4s; animation-duration: 7s; }
.bubble--4 { animation-delay: 0.6s; animation-duration: 6.5s; }
.bubble--5 { animation-delay: 3.1s; animation-duration: 5.5s; }

.spark { fill: var(--color-primary-strong); transform-box: fill-box; transform-origin: center; animation: pulse-spark 3s ease-in-out infinite; }
.spark--2 { animation-delay: 1.4s; }

.note-card { fill: var(--color-surface); stroke: var(--color-border); stroke-width: 2; filter: drop-shadow(0 8px 14px color-mix(in srgb, var(--color-text) 16%, transparent)); }
.note-dot { fill: var(--color-success); }
.note-check { fill: none; stroke: #fff; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
.note-title { fill: var(--color-text); font-size: 12px; font-weight: 700; }
.note-sub { fill: var(--color-muted); font-size: 11px; }

@keyframes wave-slide { to { transform: translateX(-48px); } }
@keyframes tumble { to { transform: rotate(360deg); } }
@keyframes bubble-rise {
    0% { opacity: 0; transform: translateY(0); }
    15% { opacity: 0.9; }
    100% { opacity: 0; transform: translateY(-46px); }
}
@keyframes pulse-spark {
    0%, 100% { opacity: 0.35; transform: scale(0.7); }
    50% { opacity: 1; transform: scale(1.15); }
}
</style>