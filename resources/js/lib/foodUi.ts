import {
    Apple,
    CupSoda,
    Cookie,
    Drumstick,
    Package,
    Salad,
    Utensils,
    Wheat,
    type LucideIcon,
} from 'lucide-vue-next';

/** Category tile icon by name (Figma 07.1 uses one illustration per category). Unknown names get a plate. */
export function categoryIcon(name: string): LucideIcon {
    const n = name.toLowerCase();
    if (n.includes('pokok')) return Wheat;
    if (n.includes('hewani')) return Drumstick;
    if (n.includes('sayur')) return Salad;
    if (n.includes('buah')) return Apple;
    if (n.includes('camilan')) return Cookie;
    if (n.includes('minum')) return CupSoda;
    if (n.includes('kemasan')) return Package;

    return Utensils;
}

/** Daily references shown on the detail page (same ones the Figma detail uses: 67 g fat, 60 g protein, 325 g carbs, 50 g sugar, 2000 mg sodium). */
export const DAILY_REFERENCE = {
    lemak: 67,
    protein: 60,
    karbohidrat: 325,
    gula: 50,
    natrium: 2000,
} as const;

export const idNumber = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 1,
});

/** Atwater estimate: 4 kcal per g protein and carbohydrate, 9 kcal per g fat. */
export function energyLooksConsistent(
    energi: number,
    protein: number,
    karbohidrat: number,
    lemak: number,
): boolean {
    const expected = 4 * (protein + karbohidrat) + 9 * lemak;
    if (expected === 0) return energi === 0;

    return Math.abs(energi - expected) <= Math.max(20, expected * 0.2);
}
