import type {
    NavMenuItem,
    HabitCardItem,
    FeatureTileItem,
    SceneCardItem,
    FamilyMemberRow,
    ArticleCategoryItem,
} from '../types/landing';

export const navMenuItems: NavMenuItem[] = [
    { label: 'Fitur', href: '#fitur' },
    { label: 'Meja Keluarga', href: '#meja-keluarga' },
    { label: 'Ahli Gizi', href: '#ahli-gizi' },
    { label: 'Bacaan', href: '#bacaan' },
];

export const habitCards: HabitCardItem[] = [
    {
        id: 'catat-makan',
        title: 'Catat makan harian',
        description: 'Pilih dari makanan rumahan, piringmu terisi sendiri.',
        actionText: 'Coba catat',
        actionHref: '/register',
        bgColor: 'bg-[#eb4416]',
        textColor: 'text-white',
        accentColor: '#eb4416',
        illustrationType: 'record-meal',
    },
    {
        id: 'pindai-label',
        title: 'Pindai label kemasan',
        description: 'Gula, lemak, dan natrium dalam bahasa sederhana.',
        actionText: 'Coba pindai',
        actionHref: '/register',
        bgColor: 'bg-[#6c4fd8]',
        textColor: 'text-white',
        accentColor: '#6c4fd8',
        illustrationType: 'scan-label',
    },
    {
        id: 'masak-bareng',
        title: 'Masak dan makan bareng',
        description: 'Meja Keluarga menunjukkan siapa yang sudah mencatat.',
        actionText: 'Lihat Meja Keluarga',
        actionHref: '#meja-keluarga',
        bgColor: 'bg-[#0b8043]',
        textColor: 'text-white',
        accentColor: '#0b8043',
        illustrationType: 'cook-together',
    },
];

export const featureTiles: FeatureTileItem[] = [
    {
        id: 'catatan-makanan',
        title: 'Catatan Makanan',
        icon: 'NotepadText',
        href: '/register',
    },
    {
        id: 'pindai-kemasan',
        title: 'Pindai Kemasan',
        icon: 'Scan',
        href: '/register',
    },
    {
        id: 'chat-gizi',
        title: 'Chat Gizi',
        icon: 'MessageCircle',
        href: '/register',
    },
    {
        id: 'kalkulator-bmi',
        title: 'Kalkulator BMI',
        icon: 'Scale',
        href: '/register',
    },
    {
        id: 'kebutuhan-kalori',
        title: 'Kebutuhan Kalori',
        icon: 'Target',
        href: '/register',
    },
    {
        id: 'data-makanan',
        title: 'Data Makanan',
        icon: 'Soup',
        href: '/register',
    },
    { id: 'artikel', title: 'Artikel', icon: 'BookOpen', href: '#bacaan' },
    {
        id: 'konsultasi-ahli-gizi',
        title: 'Konsultasi Ahli Gizi',
        icon: 'Stethoscope',
        href: '/register',
    },
];

export const sceneCards: SceneCardItem[] = [
    {
        id: 'scene-makanan',
        badge: 'Data Makanan',
        title: 'Makanan rumahan, nilai gizinya jelas',
        description: 'Tempe, sayur asem, sampai soto ayam, diverifikasi Admin.',
        linkText: 'Buka Data Makanan',
        linkHref: '/register',
        bgTint: 'bg-[#e4f6ea]',
        badgeColor: 'bg-[#0b8043] text-white',
        illustrationType: 'market',
    },
    {
        id: 'scene-konsultasi',
        badge: 'Konsultasi',
        title: 'Tanya Ahli Gizi lewat chat atau video',
        description:
            'Bagikan catatan 7 hari kalau mau, lalu jadwalkan meeting.',
        linkText: 'Lihat Ahli Gizi',
        linkHref: '/register',
        bgTint: 'bg-[#eee9ff]',
        badgeColor: 'bg-[#6c4fd8] text-white',
        illustrationType: 'consultation',
    },
    {
        id: 'scene-bacaan',
        badge: 'Artikel',
        title: 'Ide bekal dan menu yang mudah',
        description: 'Bacaan 3 sampai 6 menit, ditulis untuk dapur keluarga.',
        linkText: 'Baca artikel',
        linkHref: '#bacaan',
        bgTint: 'bg-[#fff4cc]',
        badgeColor: 'bg-[#c7370c] text-white',
        illustrationType: 'recipes',
    },
];

export const familyTableDays = ['Sn', 'Sl', 'Rb', 'Km', 'Jm', 'Sb', 'Mg'];

export const familyMembersData: FamilyMemberRow[] = [
    {
        id: 'budi',
        name: 'Budi',
        role: 'Anggota',
        avatarColor: 'bg-[#0b8043]',
        initials: 'BD',
        logs: [
            { day: 'Sn', recorded: true },
            { day: 'Sl', recorded: true },
            { day: 'Rb', recorded: true },
            { day: 'Km', recorded: true },
            { day: 'Jm', recorded: true },
            { day: 'Sb', recorded: false },
            { day: 'Mg', recorded: false },
        ],
        totalDays: 5,
        targetMet: true,
    },
    {
        id: 'rina',
        name: 'Rina',
        role: 'Kepala Keluarga',
        avatarColor: 'bg-[#eb4416]',
        initials: 'RN',
        logs: [
            { day: 'Sn', recorded: true },
            { day: 'Sl', recorded: true },
            { day: 'Rb', recorded: true },
            { day: 'Km', recorded: true },
            { day: 'Jm', recorded: true },
            { day: 'Sb', recorded: true },
            { day: 'Mg', recorded: false },
        ],
        totalDays: 6,
        targetMet: true,
    },
    {
        id: 'raka',
        name: 'Raka',
        role: 'Anggota',
        avatarColor: 'bg-[#6c4fd8]',
        initials: 'RK',
        logs: [
            { day: 'Sn', recorded: true },
            { day: 'Sl', recorded: true },
            { day: 'Rb', recorded: false },
            { day: 'Km', recorded: true },
            { day: 'Jm', recorded: true },
            { day: 'Sb', recorded: false },
            { day: 'Mg', recorded: false },
        ],
        totalDays: 4,
        targetMet: false,
    },
    {
        id: 'dina',
        name: 'Dina',
        role: 'Anggota · 17 tahun',
        avatarColor: 'bg-[#ffc933]',
        initials: 'DN',
        logs: [
            { day: 'Sn', recorded: true },
            { day: 'Sl', recorded: true },
            { day: 'Rb', recorded: true },
            { day: 'Km', recorded: true },
            { day: 'Jm', recorded: false },
            { day: 'Sb', recorded: true },
            { day: 'Mg', recorded: false },
        ],
        totalDays: 5,
        targetMet: true,
    },
];

export const articleCategories: ArticleCategoryItem[] = [
    {
        id: 'gizi-anak',
        name: 'Gizi Anak',
        imagePlaceholderBg: 'from-amber-200 to-orange-300',
        tag: 'Nutrisi Tumbuh Kembang',
    },
    {
        id: 'label-kemasan',
        name: 'Label Kemasan',
        imagePlaceholderBg: 'from-blue-200 to-indigo-300',
        tag: 'Pilah Camilan Sehat',
    },
    {
        id: 'resep-sehat',
        name: 'Resep Sehat',
        subtitle: 'Piring bergizi seimbang, mulai dari dapur sendiri',
        isFeatured: true,
        imagePlaceholderBg: 'from-orange-500 to-red-600',
        tag: 'Pilihan Utama Dapur',
    },
    {
        id: 'gizi-keluarga',
        name: 'Gizi Keluarga',
        imagePlaceholderBg: 'from-emerald-200 to-teal-300',
        tag: 'Menu Harian Kompak',
    },
    {
        id: 'penyakit-gizi',
        name: 'Penyakit dan Gizi',
        imagePlaceholderBg: 'from-purple-200 to-pink-300',
        tag: 'Diet Khusus & Terapi',
    },
];
