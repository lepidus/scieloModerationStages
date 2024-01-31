import '../support/commands.js';

describe('SciELO Moderation Stages - Exhibitors on submissions page', function () {
    let submission1 = "Night of the Living Dead";
    let submission2 = "Ju-on: The Grudge";
    
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

        cy.get('.listPanel__itemSubtitle:visible:contains("' + submission2 + '")')
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
            });

        cy.get('.listPanel__itemSubtitle:visible:contains("' + submission2 + '")')
            .parent().within(() => {
                cy.contains('Moderation stage:');
                cy.contains('Manuscript Type Pre-Moderation');
            });
    });
});