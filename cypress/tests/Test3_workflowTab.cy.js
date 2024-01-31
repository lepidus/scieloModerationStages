import '../support/commands.js';

describe("SciELO Moderation Stages - Workflow tab", function() {
    let submissionData;
    let today;
    let yesterday;
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 4000);
        today = (new Date()).toISOString().split('T')[0];
        yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        yesterday = yesterday.toISOString().split('T')[0];
        
        submissionData = {
            title: "Ju-on: The Grudge",
			abstract: 'A house were terrible things happened',
			keywords: ['plugin', 'testing'],
            contributors: [
                {
                    'given': 'Takashi',
                    'family': 'Shimizu',
                    'email': 'takashi.shimizu@stab.com',
                    'country': 'Japan'
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
    it("Author views entry date for each moderation stage", function() {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.get('#scieloModerationStages-button').click();
        cy.get('#formatStageEntryDateDiv').within(() => {
            cy.contains('label', 'Format Pre-Moderation');
            cy.contains('label', 'The submission has entered in this moderation stage in the following data');
            cy.get('input[name="formatStageEntryDate"]').should('have.value', today);
            cy.get('input[name="formatStageEntryDate"]').should('be.disabled');
        });
        cy.get('#moderationStageSubmit').should('not.exist');
    });
    it("Editor edits submission's entry dates", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.get('#publication-button').click();
        cy.get('#scieloModerationStages-button').click();

        cy.get('#formatStageEntryDateDiv').within(() => {
            cy.get('input[name="formatStageEntryDate"]').should('have.value', today);
            cy.get('input[name="formatStageEntryDate"]').type(yesterday);
        });
        cy.get('#moderationStageSubmit').click();

        cy.reload();
        cy.get('#formatStageEntryDateDiv').within(() => {
            cy.get('input[name="formatStageEntryDate"]').should('have.value', yesterday);
        });
    });
    it("Editor advances moderation stage in workflow tab", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.get('#publication-button').click();
        cy.get('#scieloModerationStages-button').click();

        cy.get('#checkboxSendNextStageMenuYes').check();
        cy.get('#moderationStageSubmit').click();

        cy.reload();
        cy.get('#formatStageEntryDateDiv');
        cy.get('#contentStageEntryDateDiv').within(() => {
            cy.get('input[name="contentStageEntryDate"]').should('have.value', today);
        });
    });
});
