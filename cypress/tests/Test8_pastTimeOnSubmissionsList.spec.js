function loginAuthorUser() {
    cy.get('input[id=username]').clear();
    cy.get('input[id=username]').type(Cypress.env('OJSAuthorUsername'), { delay: 0 });
    cy.get('input[id=password]').type(Cypress.env('OJSAuthorPassword'), { delay: 0 });
    cy.get('button[class=submit]').click();
}

function loginAdminUser() {
    cy.get('input[id=username]').clear();
    cy.get('input[id=username]').type(Cypress.env('OJSAdminUsername'), { delay: 0 });
    cy.get('input[id=password]').type(Cypress.env('OJSAdminPassword'), { delay: 0 });
    cy.get('button[class=submit]').click();
}

function userLogout() {
    cy.get(".pkpDropdown.app__headerAction > .pkpButton").click();
    cy.get("a.pkpDropdown__action").contains("Logout").click();
}

function submissionStep1() {
    cy.get('#sectionId').select('1');
    cy.get('#pkp_submissionChecklist > ul > li > label > input').check();
    cy.get('#privacyConsent').check();

    cy.get('#submissionStep1 > .formButtons > .submitFormButton').click();
}

function submissionStep2() {
    cy.get('#submitStep2Form > .formButtons > .submitFormButton').click();
}

function submissionStep3() {
    cy.get('input[name^="title"]').first().type("Submission test past time in submissions listing", { delay: 0 });
    cy.get('label').contains('Title').click();
    cy.get('textarea[id^="abstract-"').then((node) => {
        cy.setTinyMceContent(node.attr("id"), "Example of abstract");
    });
    cy.get('.section > label:visible').first().click();
    cy.get('ul[id^="en_US-keywords-"]').then(node => {
        node.tagit('createTag', "Dummy keyword");
    });
    cy.get('#submitStep3Form > .formButtons > .submitFormButton').click();
}

function submissionStep4() {
    cy.get('#submitStep4Form > .formButtons > .submitFormButton').click();
    cy.get('.pkp_modal_confirmation > .footer > .ok').click();
}

function assignUser(userGroupName) {
    cy.get('a[id^="component-grid-users-stageparticipant-stageparticipantgrid-requestAccount"]').contains("Assign").click();
    cy.get('select[name^="filterUserGroupId"]').select(userGroupName);
    cy.get('button').contains('Search').click();
    cy.wait(500);
    cy.get('tr[id^="component-grid-users-userselect-userselectgrid-row"] > .first_column > input').first().click();
    cy.get('#checkboxSendNextStageAssignNo').click();
    cy.get("#addParticipantForm > .formButtons > .submitFormButton").click();
    cy.wait(3000);
}

function assignResponsibleUser() {
    assignUser('Responsible');
}

function assignAreaModeratorUser() {
    assignUser('Area Moderator');
}

describe("SciELO Moderation Stages Plugin - Past time exhibitors in submissions listing page", function() {
    it("Author user submits", function() {
        cy.visit(Cypress.env('baseUrl') + 'index.php/scielo/submissions');
        loginAuthorUser();

        cy.get('.pkpHeader__actions:visible > a.pkpButton').click();
        submissionStep1();
        submissionStep2();
        submissionStep3();
        submissionStep4();
        userLogout();
    });
    it("Assign responsible and area moderator users", function() {
        loginAdminUser();
        cy.wait(3000);
        cy.get("#active-button").click();
        cy.get("a.pkpButton:visible").contains("View").first().click();
        assignResponsibleUser();
        cy.reload();
        assignAreaModeratorUser();
    });
    it("Check if past time exhibitors appear in submissions listing", function() {
        cy.get(".app__navItem").contains("Submissions").click();
        cy.get("#active-button").click();
        cy.get(".listPanel__itemIdentity:visible > .listPanel__itemTimeSubmitted")
            .first()
            .contains('Submission made less than a day ago');
        cy.get(".listPanel__itemIdentity:visible > .listPanel__itemTimeResponsible")
            .first()
            .contains('Responsible assigned less than a day ago');
        cy.get(".listPanel__itemIdentity:visible > .listPanel__itemTimeAreaModerator")
            .first()
            .contains('Area moderator assigned less than a day ago');
    });
});