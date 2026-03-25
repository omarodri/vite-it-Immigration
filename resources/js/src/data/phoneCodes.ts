export interface PhoneCode {
    code: string;
    country: string;
    label: string;
}

export const PHONE_CODES: PhoneCode[] = [
    { code: '+1', country: 'CA', label: 'Canada / USA (+1)' },
    { code: '+52', country: 'MX', label: 'México (+52)' },
    { code: '+57', country: 'CO', label: 'Colombia (+57)' },
    { code: '+55', country: 'BR', label: 'Brasil (+55)' },
    { code: '+58', country: 'VE', label: 'Venezuela (+58)' },
    { code: '+51', country: 'PE', label: 'Perú (+51)' },
    { code: '+56', country: 'CL', label: 'Chile (+56)' },
    { code: '+54', country: 'AR', label: 'Argentina (+54)' },
    { code: '+593', country: 'EC', label: 'Ecuador (+593)' },
    { code: '+53', country: 'CU', label: 'Cuba (+53)' },
    { code: '+509', country: 'HT', label: 'Haití (+509)' },
    { code: '+1-876', country: 'JM', label: 'Jamaica (+1-876)' },
    { code: '+33', country: 'FR', label: 'France (+33)' },
    { code: '+44', country: 'GB', label: 'United Kingdom (+44)' },
    { code: '+91', country: 'IN', label: 'India (+91)' },
    { code: '+86', country: 'CN', label: 'China (+86)' },
    { code: '+63', country: 'PH', label: 'Philippines (+63)' },
    { code: '+234', country: 'NG', label: 'Nigeria (+234)' },
    { code: '+212', country: 'MA', label: 'Morocco (+212)' },
    { code: '+213', country: 'DZ', label: 'Algeria (+213)' },
];
