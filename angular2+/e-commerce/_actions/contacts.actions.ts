// NGRX
import { Action } from '@ngrx/store';
// CRUD
import { QueryParamsModel } from '../../_base/crud';
// Models
import { ContactsModel } from '../_models/contacts.model';
import { Update } from '@ngrx/entity';

export enum ContactsActionTypes {
	ContactsOnServerCreated = '[Edit Contacts Component] Contacts On Server Created',
	ContactsCreated = '[Edit Contacts Component] Contacts Created',
	ContactsUpdated = '[Edit Contacts Component] Contacts Updated',
	ContactsStatusUpdated = '[Contacts List Page] Contacts Status Updated',
	OneContactsDeleted = '[Contacts List Page] One Contacts Deleted',
	ManyContactsDeleted = '[Contacts List Page] Many Selected Contacts Deleted',
	ContactsPageRequested = '[Contacts List Page] Contacts Page Requested',
	ContactsPageLoaded = '[Contacts API] Contacts Page Loaded',
	ContactsPageCancelled = '[Contacts API] Contacts Page Cancelled',
	ContactsPageToggleLoading = '[Contacts] Contacts Page Toggle Loading',
	ContactsActionToggleLoading = '[Contacts] Contacts Action Toggle Loading'
}

export class ContactOnServerCreated implements Action {
	readonly type = ContactsActionTypes.ContactsOnServerCreated;
	constructor(public payload: { contact: ContactsModel }) { }
}

export class ContactCreated implements Action {
	readonly type = ContactsActionTypes.ContactsCreated;
	constructor(public payload: { contact: ContactsModel }) { }
}

export class ContactUpdated implements Action {
	readonly type = ContactsActionTypes.ContactsUpdated;
	constructor(public payload: {
		partialContacts: Update<ContactsModel>, // For State update
		contact: ContactsModel // For Server update (through service)
	}) { }
}

export class ContactStatusUpdated implements Action {
	readonly type = ContactsActionTypes.ContactsStatusUpdated;
	constructor(public payload: {
		contacts: ContactsModel[],
		status: number
	}) { }
}

export class OneContactDeleted implements Action {
	readonly type = ContactsActionTypes.OneContactsDeleted;
	constructor(public payload: { id: number }) {}
}

export class ManyContactsDeleted implements Action {
	readonly type = ContactsActionTypes.ManyContactsDeleted;
	constructor(public payload: { ids: number[] }) {}
}

export class ContactPageRequested implements Action {
	readonly type = ContactsActionTypes.ContactsPageRequested;
	constructor(public payload: { page: QueryParamsModel }) { }
}

export class ContactPageLoaded implements Action {
	readonly type = ContactsActionTypes.ContactsPageLoaded;
	constructor(public payload: { contacts: ContactsModel[], totalCount: number, page: QueryParamsModel }) { }
}

export class ContactPageCancelled implements Action {
	readonly type = ContactsActionTypes.ContactsPageCancelled;
}

export class ContactPageToggleLoading implements Action {
	readonly type = ContactsActionTypes.ContactsPageToggleLoading;
	constructor(public payload: { isLoading: boolean }) { }
}

export class ContactActionToggleLoading implements Action {
	readonly type = ContactsActionTypes.ContactsActionToggleLoading;
	constructor(public payload: { isLoading: boolean }) { }
}

export type ContactsActions = ContactOnServerCreated
	| ContactCreated
	| ContactUpdated
	| ContactStatusUpdated
	| ManyContactsDeleted
	| ContactPageRequested
	| ContactPageLoaded
	| ContactPageCancelled
	| ContactPageToggleLoading
	| ContactActionToggleLoading;
