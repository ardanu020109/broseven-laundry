import {
    CheckCircle2,
    ClipboardList,
    Clock,
    Droplets,
    MessageCircle,
    PackageCheck,
    QrCode,
    RefreshCw,
    Scale,
    ShieldCheck,
    Shirt,
    Sparkles,
    Truck,
    Wind,
} from 'lucide-vue-next';

/** Kunci ikon sama dengan App\Support\ProcedureIcons di backend. */
const ICONS = {
    shirt: Shirt,
    scale: Scale,
    'clipboard-list': ClipboardList,
    droplets: Droplets,
    'refresh-cw': RefreshCw,
    'message-circle': MessageCircle,
    'qr-code': QrCode,
    sparkles: Sparkles,
    truck: Truck,
    'package-check': PackageCheck,
    clock: Clock,
    wind: Wind,
    'check-circle': CheckCircle2,
    'shield-check': ShieldCheck,
};

export function procedureIcon(name) {
    return ICONS[name] ?? Sparkles;
}