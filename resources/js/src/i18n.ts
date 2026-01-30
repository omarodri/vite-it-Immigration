import { createI18n } from 'vue-i18n';

// Import locale messages
import en from '@/locales/en.json';
// import ae from '@/locales/ae.json';
// import da from '@/locales/da.json';
// import de from '@/locales/de.json';
// import el from '@/locales/el.json';
import es from '@/locales/es.json';
import fr from '@/locales/fr.json';
// import hu from '@/locales/hu.json';
// import it from '@/locales/it.json';
// import ja from '@/locales/ja.json';
// import pl from '@/locales/pl.json';
// import pt from '@/locales/pt.json';
// import ru from '@/locales/ru.json';
// import sv from '@/locales/sv.json';
// import tr from '@/locales/tr.json';
// import zh from '@/locales/zh.json';

const messages = {
    en,
    // ae,
    // da,
    // de,
    // el,
    es,
    fr,
    // hu,
    // it,
    // ja,
    // pl,
    // pt,
    // ru,
    // sv,
    // tr,
    // zh,
};

export default createI18n({
    legacy: false,
    allowComposition: true,
    locale: 'en',
    globalInjection: true,
    fallbackLocale: 'en',
    messages,
});
