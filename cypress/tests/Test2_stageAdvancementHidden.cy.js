import '../support/commands.js';

function checkSendNextStageOptionIsNotPresent() {
    cy.contains("This submission is in the Format Pre-Moderation stage").should('not.exist');
    cy.get('#checkboxSendNextStageAssignYes').should('not.exist');
    cy.get('#checkboxSendNextStageAssignNo').should('not.exist');
}

describe("SciELO Moderation Stages - Stage advancement hidden scenarios", function() {
    let submissionData;
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 4000);
        submissionData = {
            title: "Candyman",
			abstract: 'A ghost appears when you speak his name 5 times in front of a mirror',
			keywords: ['plugin', 'testing'],
            contributors: [
                {
                    'given': 'Clive',
                    'family': 'Barker',
                    'email': 'clive.barker@stab.com',
                    'country': 'United Kingdom'
                }
            ],
            files: [
                {
                    'file': 'dummy.pdf',
                    'fileName': 'dummy.pdf',
                    'mimeType': 'application/pdf',
                    'genre': 'Preprint Text'
                }
            ]
		};
    });
    
    it("Author creates submission", function() {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.createSubmission(submissionData);
    });
    it("Sending of submission to next moderation stage does not appear when posted/declined", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.get('#publication-button').click();
        cy.get('.pkpHeader__actions button:contains("Post")').click();
        cy.get('.pkp_modal_panel button:contains("Post")').click();
        cy.waitJQuery();
        cy.contains('span', 'Posted');
        cy.reload();
        
        cy.get('#workflow-button').click();
        cy.contains('a', 'Assign').click();
        checkSendNextStageOptionIsNotPresent();
        cy.get('a.pkpModalCloseButton:visible').click();
        cy.on('window:confirm', () => true);

        cy.get('#publication-button').click();
		cy.get('.pkpHeader__actions button:contains("Unpost")').click();
        cy.get('.modal__panel button:contains("Unpost")').click();
        cy.waitJQuery();
        cy.contains('span', 'Unposted');
        cy.reload();

        cy.get('#workflow-button').click();
        cy.contains('a', 'Decline Submission').click();
        cy.contains('button', 'Skip this email').click();
        cy.contains('button', 'Record Decision').click();
        cy.contains('a', 'View Submission').click();

        cy.contains('a', 'Assign').click();
        checkSendNextStageOptionIsNotPresent();
    });
});
