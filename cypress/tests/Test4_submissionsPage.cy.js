import '../support/commands.js';

describe('SciELO Moderation Stages - Features on submissions page', function () {
    let submission1 = "Night of the Living Dead";
    let submission2 = 'Candyman';
    let submission3 = "Ju-on: The Grudge";
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 4000);
    });

    it("Authors can only view submissions' moderation stage", function () {
        cy.login('fpaglieri', null, 'publicknowledge');

        cy.waitJQuery();
        cy.get('.listPanel__itemSubtitle:visible:contains("' + submission1 + '")')
            .parent().within(() => {
                cy.contains('Moderation stage:');
                cy.contains('Area Moderation');
            });

        cy.get('.listPanel__itemSubtitle:visible:contains("' + submission3 + '")')
            .parent().within(() => {
                cy.contains('Moderation stage:');
                cy.contains('Manuscript Type Pre-Moderation');
            });
    });
    it("Editor can view all submissions' exhibitors", function () {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.get('#active-button').click();

        cy.waitJQuery();
        cy.get('.listPanel__itemSubtitle:visible:contains("' + submission1 + '")')
            .parent().within(() => {
                cy.contains('Moderation stage:');
                cy.contains('Area Moderation');

                cy.contains('Submission made less than a day ago');
            });

        cy.get('.listPanel__itemSubtitle:visible:contains("' + submission3 + '")')
            .parent().within(() => {
                cy.contains('Moderation stage:');
                cy.contains('Manuscript Type Pre-Moderation');

                cy.contains('Submission made less than a day ago');
            });
    });
    it("Editor can filter submissions by moderation stage", function () {
        cy.login('dbarnes', null, 'publicknowledge');

        cy.get('#active-button').click();
        cy.wait(1000);
        cy.contains('.pkpButton', 'Filters').click();
        cy.contains('h4', 'Moderation Stages');

        cy.contains('.pkpFilter__label', 'Area Moderation').click();
        cy.waitJQuery();
        cy.get('li.listPanel__item:visible').should('have.length', 1);
        cy.contains(submission1);

        cy.contains('.pkpFilter__label', 'Manuscript Type Pre-Moderation').click();
        cy.waitJQuery();
        cy.get('li.listPanel__item:visible').should('have.length', 2);
        cy.contains(submission1);
        cy.contains(submission3);

        cy.get('#archive-button').click();
        cy.contains('.pkpButton', 'Filters').click();
        cy.contains('h4', 'Moderation Stages');

        cy.contains('.pkpFilter__label', 'Format Pre-Moderation').click();
        cy.waitJQuery();
        cy.get('li.listPanel__item:visible').should('have.length', 1);
        cy.contains(submission2);
    });
});