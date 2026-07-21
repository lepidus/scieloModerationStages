import '../support/commands.js';

describe("SciELO Moderation Stages - Workflow tab", function() {
    let submissionData;
    let today;
    let yesterday;
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 10000);
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

        cy.openWorkflowMenu('Moderation Stages');

        cy.contains('Your submission is currently at the Format Pre-Moderation stage, where it is undergoing a screening process');
        cy.contains('Please wait for a response from the editorial team or an update on the status of your submission');
        cy.get('[data-cy="formatStageEntryDateDiv"]').within(() => {
            cy.contains('label', 'Format Pre-Moderation');
            cy.contains('The submission has entered in this moderation stage in the following data');
            cy.get('input[name="formatStageEntryDate"]').should('have.value', today);
            cy.get('input[name="formatStageEntryDate"]').should('be.disabled');
        });
        cy.get('[data-cy="moderationStageSubmit"]').should('not.exist');
    });
    it("Editor edits submission's entry dates", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.openWorkflowMenu('Moderation Stages');

        cy.get('[data-cy="formatStageEntryDateDiv"]').within(() => {
            cy.get('input[name="formatStageEntryDate"]').should('have.value', today);
            cy.get('input[name="formatStageEntryDate"]').clear().type(yesterday);
        });
        cy.get('[data-cy="moderationStageSubmit"] button').click();
        cy.get('[data-cy="moderationStageSaveMessage"]').should('contain', 'Saved');

        cy.reload();
        cy.get('[data-cy="formatStageEntryDateDiv"]').within(() => {
            cy.get('input[name="formatStageEntryDate"]').should('have.value', yesterday);
        });
    });
    it("Editor advances moderation stage in workflow tab", function() {
        cy.login('dbarnes', null, 'publicknowledge');
        cy.findSubmission('active', submissionData.title);

        cy.openWorkflowMenu('Moderation Stages');

        cy.get('[data-cy="stageChangeAdvance"]').check();
        cy.get('[data-cy="moderationStageSubmit"] button').click();
        cy.get('[data-cy="moderationStageSaveMessage"]').should('contain', 'Saved');

        cy.reload();
        cy.get('[data-cy="formatStageEntryDateDiv"]');
        cy.get('[data-cy="contentStageEntryDateDiv"]').within(() => {
            cy.get('input[name="contentStageEntryDate"]').should('have.value', today);
        });
    });
    it("Checks display of stage description to author after advancing moderation stage", function () {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.openWorkflowMenu('Moderation Stages');

        cy.contains('Your submission is currently at the Manuscript Type Pre-Moderation stage and is undergoing a review');
        cy.contains('For more information, we recommend reading our FAQs #10 and #19');
    });
    it("Checks sending of email notification after advancing moderation stage", function() {
        cy.visit('http://127.0.0.1:8025');
        cy.contains('b', 'Advancement in Submission Moderation')
            .first()
            .parent().parent().parent()
            .within((node) => {
                cy.contains('fpaglieri@mailinator.com');
            });
        cy.contains('b', 'Advancement in Submission Moderation').first().click();
        cy.get('#nav-tab button:contains("Text")').click();

        cy.contains('Your submission has been forwarded to the Manuscript Type Pre-Moderation stage');
        cy.contains('To facilitate moderation, please provide an updated ORCID with the most recent scientific output for at least one of the authors registered in the submission');
        cy.contains('Optionally, you may also provide an endorsement for the preprint, if you have one');
        cy.contains('For more information, we recommend reading our FAQs #10 and #19');
    });
});
