import '../support/commands.js';

describe("SciELO Moderation Stages - Moderation stage advancement", function() {
    let submissionData;
    
    before(function() {
        Cypress.config('defaultCommandTimeout', 4000);
        submissionData = {
            title: "Night of the Living Dead",
			abstract: 'Some people get stuck in a house when the dead arise from their graves',
			keywords: ['plugin', 'testing'],
            contributors: [
                {
                    'given': 'George',
                    'family': 'Romero',
                    'email': 'george.romero@stab.com',
                    'country': 'United States'
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
    it("Checks submission is set to first moderation stage", function() {
        cy.login('fpaglieri', null, 'publicknowledge');
        cy.findSubmission('myQueue', submissionData.title);

        cy.contains('strong', 'Moderation stage:');
        cy.contains('span', 'Format Pre-Moderation');
    });
});
