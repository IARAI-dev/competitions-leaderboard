jQuery(document).ready((event) => {

    const termLockEditCheckbox = jQuery('input[name="term-locks[edit]"]');

    if (
        typeof termLockEditCheckbox === 'undefined' ||
        !termLockEditCheckbox ||
        termLockEditCheckbox.length === 0
    ) { return; }

    termLockEditCheckbox.on('change', (event) => {

        if (!termLockEditCheckbox.prop('checked')) { 
            sessionStorage.setItem('iaraiCompetitionLocked', 'false');   
        } else {
            sessionStorage.setItem('iaraiCompetitionLocked', 'true');    
        }

        return;
    });

    if (termLockEditCheckbox.prop('checked')) { 
        const iaraiCompetitionLocked = sessionStorage.getItem('iaraiCompetitionLocked');

        if (
            typeof iaraiCompetitionLocked === 'undefined' ||
            !iaraiCompetitionLocked ||
            iaraiCompetitionLocked === 'false'
        ) { 
            sessionStorage.setItem('iaraiCompetitionLocked', 'true');
        }

        jQuery(document).on('click', 'a', (event) => {
            event.preventDefault();

            const iaraiCompetitionLocked = sessionStorage.getItem('iaraiCompetitionLocked');

            if (
                typeof iaraiCompetitionLocked === 'undefined' ||
                !iaraiCompetitionLocked
            ) { return event; }

            const linkTarget = jQuery(event.target);
            const link = linkTarget.attr('href');

            if (link !== window.location.href) {
                const alertConfirmed = confirm('Please make sure that the Competition is unlocked before leaving.\nThank you!');

                const termLockEditSpan = jQuery('.term-lock-edit');

                termLockEditSpan.css({'color': 'red', 'font-weight': '600'});

                jQuery('html, body').animate({
                    scrollTop: jQuery('.term-lock-wrap').offset().top - termLockEditSpan.height(),
                }, 250);

                return false;
            } else {
                return event;
            }
        });

        return; 
    } else {
        const iaraiCompetitionLocked = sessionStorage.getItem('iaraiCompetitionLocked');

        if (
            typeof iaraiCompetitionLocked !== 'undefined' &&
            iaraiCompetitionLocked === 'false'
        ) { 
            sessionStorage.removeItem('iaraiCompetitionLocked');
            return;
        }

        const alertConfirmed = confirm('Please make sure to lock the Competition for other Editors before editing.\nOnce you click "OK", the update should happen automatically.\nPlease wait until the page refreshes.\nThank you!');

        if (alertConfirmed) {
            const updateButton = jQuery('.edit-tag-actions input[type="submit"]');

            updateButton
                .attr('disabled', 'disabled')
                .css({'pointer-events': 'none'});

            setTimeout(() => {
                termLockEditCheckbox.prop('checked', true);
                termLockEditCheckbox.trigger('change');

                updateButton
                    .removeAttr('disabled')
                    .css({'pointer-events': 'initial'})
                    .trigger('click')
                    .attr('disabled', 'disabled')
                    .css({'pointer-events': 'none'});
            }, 500);
        }
    }
});