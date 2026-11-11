export interface NavMenuItem {
    label: string;
    href: string;
}

export interface HabitCardItem {
    id: string;
    title: string;
    description: string;
    actionText: string;
    actionHref: string;
    bgColor: string;
    textColor: string;
    accentColor: string;
    illustrationType: 'record-meal' | 'scan-label' | 'cook-together';
}

export interface FeatureTileItem {
    id: string;
    title: string;
    icon: string;
    href: string;
}

export interface SceneCardItem {
    id: string;
    badge: string;
    title: string;
    description: string;
    linkText: string;
    linkHref: string;
    bgTint: string;
    badgeColor: string;
    illustrationType: 'market' | 'consultation' | 'recipes';
}

export interface FamilyMemberDayLog {
    day: string; // 'Sn' | 'Sl' | 'Rb' | 'Km' | 'Jm' | 'Sb' | 'Mg'
    recorded: boolean;
}

export interface FamilyMemberRow {
    id: string;
    name: string;
    role: string;
    avatarColor: string;
    initials: string;
    logs: FamilyMemberDayLog[];
    totalDays: number;
    targetMet: boolean;
}

export interface ArticleCategoryItem {
    id: string;
    name: string;
    subtitle?: string;
    isFeatured?: boolean;
    imagePlaceholderBg: string;
    tag: string;
}
