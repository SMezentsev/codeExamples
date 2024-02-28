import { forkJoin } from 'rxjs';
// Angular
import { Injectable } from '@angular/core';
// RxJS
import { mergeMap, map, tap } from 'rxjs/operators';
// NGRX
import { Effect, Actions, ofType } from '@ngrx/effects';
import { Store, Action } from '@ngrx/store';
// CRUD
import { QueryResultsModel, QueryParamsModel } from '../../_base/crud';
// Services
import { ContactsService } from '../_services/';
// State
import { AppState } from '../../../core/reducers';
// Actions
import {
	ContactActionTypes,
	ContactsPageRequested,
	ContactsPageLoaded,
	ManyContactsDeleted,
	OneContactDeleted,
	ContactsPageToggleLoading,
	ContactsStatusUpdated,
	ContactUpdated,
	ContactCreated,
	ContactOnServerCreated
} from '../_actions/contacts.actions';
import { defer, Observable, of } from 'rxjs';

@Injectable()
export class ContactEffects {
	showPageLoadingDistpatcher = new ContactsPageToggleLoading({ isLoading: true });
	showLoadingDistpatcher = new ContactsPageToggleLoading({ isLoading: true });
	hideActionLoadingDistpatcher = new ContactsPageToggleLoading({ isLoading: false });

	@Effect()
	loadContactsPage$ = this.actions$
		.pipe(
			ofType<ContactsPageRequested>(ContactActionTypes.ContactsPageRequested),
			mergeMap(( { payload } ) => {
				this.store.dispatch(this.showPageLoadingDistpatcher);
				const requestToServer = this.contactsService.findContacts(payload.page);
				const lastQuery = of(payload.page);
				return forkJoin(requestToServer, lastQuery);
			}),
			map(response => {
				const result: QueryResultsModel = response[0];
				const lastQuery: QueryParamsModel = response[1];

				console.log('result',result)

				return new ContactsPageLoaded({
					contacts: result.items,
					totalCount: result.totalCount,
					page: lastQuery
				});
			}),
		);

	@Effect()
	deleteContact$ = this.actions$
		.pipe(
			ofType<OneContactDeleted>(ContactActionTypes.OneContactDeleted),
			mergeMap(( { payload } ) => {
					this.store.dispatch(this.showLoadingDistpatcher);
					return this.contactsService.deleteContact(payload.id);
				}
			),
			map(() => {
				return this.hideActionLoadingDistpatcher;
			}),
		);

	@Effect()
	deleteContacts$ = this.actions$
		.pipe(
			ofType<ManyContactsDeleted>(ContactActionTypes.ManyContactsDeleted),
			mergeMap(( { payload } ) => {
					this.store.dispatch(this.showLoadingDistpatcher);
					return this.contactsService.deleteContacts(payload.ids);
				}
			),
			map(() => {
				return this.hideActionLoadingDistpatcher;
			}),
		);

	@Effect()
	updateContactsStatus$ = this.actions$
		.pipe(
			ofType<ContactsStatusUpdated>(ContactActionTypes.ContactsStatusUpdated),
			mergeMap(( { payload } ) => {
				this.store.dispatch(this.showLoadingDistpatcher);
				return this.contactsService.updateStatusForContact(payload.contacts, payload.status);
			}),
			map(() => {
				return this.hideActionLoadingDistpatcher;
			}),
		);

	@Effect()
	updateContact$ = this.actions$
		.pipe(
			ofType<ContactUpdated>(ContactActionTypes.ContactUpdated),
			mergeMap(( { payload } ) => {
				this.store.dispatch(this.showLoadingDistpatcher);
				return this.contactsService.updateContact(payload.contact);
			}),
			map(() => {
				return this.hideActionLoadingDistpatcher;
			}),
		);

	@Effect()
	createContact$ = this.actions$
		.pipe(
			ofType<ContactOnServerCreated>(ContactActionTypes.ContactOnServerCreated),
			mergeMap(( { payload } ) => {
				this.store.dispatch(this.showLoadingDistpatcher);
				return this.contactsService.createContact(payload.contact).pipe(
					tap(res => {
						this.store.dispatch(new ContactCreated({ contact: res }));
					})
				);
			}),
			map(() => {
				return this.hideActionLoadingDistpatcher;
			}),
		);

	// @Effect()
	// init$: Observable<Action> = defer(() => {
	//     const queryParams = new QueryParamsModel({});
	//     return of(new ContactsPageRequested({ page: queryParams }));
	// });

	constructor(private actions$: Actions, private contactsService: ContactsService, private store: Store<AppState>) { }
}
