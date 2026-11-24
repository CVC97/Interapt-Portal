// The personal code ("Codename") that links a student's module data to their survey answers without a name.
// The browser builds it from the student's answers and sends only the code: the answers themselves (birth month,
// place, ...) never leave the browser. The server stores the code with the account, checks it on a login from a
// new device (it replaces a password) and never sends it back.
//
// PLACEHOLDER (2026-11-17): the parts below are Carlo's example. Before the first course use, replace them with
// the surveys' scheme, in the surveys' wording and order: a student must do here exactly what they do on the
// questionnaire, or the codes won't match. Watch out in the example: the first two letters of month names are
// not unique (Juni and Juli → JU, März and Mai → MA).
//
// Each part:
//   label    the question, as on the questionnaire, without a number (the login page numbers the parts)
//   hint     how to answer (one line)
//   input    'month' (a dropdown) or 'letters' (a short text field with `length` letters)
//   code     value → this part's piece of the code, or null if the value is incomplete
//   confirm  value → a sentence read back before the account is created ("Sie sind im Juli geboren.")
// The server only checks that a code is 2–32 upper-case letters and digits (validCodename() in api/lib.php).

const CODENAME = (() => {
    const MONTHS = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September',
                    'Oktober', 'November', 'Dezember'];

    // upper case, ß → S, accents and umlauts to the base letter (Ü → U), everything except A–Z dropped
    const letters = text => text.replace(/ß/g, 's').normalize('NFD').toUpperCase().replace(/[^A-Z]/g, '');

    const month = m => MONTHS[m - 1];

    const parts = [
        {
            id: 'own-month',
            label: 'Ihr Geburtsmonat',
            hint: 'Für den Code zählen die ersten beiden Buchstaben des Monats.',
            input: 'month',
            code: m => letters(month(m)).slice(0, 2),
            confirm: m => `Sie sind im ${month(m)} geboren.`,
        },
        {
            id: 'birthplace',
            label: 'Die letzten beiden Buchstaben Ihres Geburtsorts',
            hint: 'Zum Beispiel Göttingen → EN.',
            input: 'letters',
            length: 2,
            code: v => (letters(v).length === 2 ? letters(v) : null),
            confirm: v => `Ihr Geburtsort endet auf „${letters(v)}“.`,
        },
        {
            id: 'mother-month',
            label: 'Der Geburtsmonat Ihrer Mutter',
            hint: 'Für den Code zählt die Monatszahl, zweistellig: März → 03.',
            input: 'month',
            code: m => String(m).padStart(2, '0'),
            confirm: m => `Ihre Mutter ist im ${month(m)} geboren.`,
        },
    ];

    // values: { partId: value } with months as numbers 1–12 and letters as typed. The code, or null while a
    // part is missing.
    function build(values) {
        let code = '';
        for (const part of parts) {
            const value = values[part.id];
            if (value === undefined || value === '' || value === null) return null;
            const piece = part.code(value);
            if (!piece) return null;
            code += piece;
        }
        return code;
    }

    return { MONTHS, parts, build, letters };
})();
