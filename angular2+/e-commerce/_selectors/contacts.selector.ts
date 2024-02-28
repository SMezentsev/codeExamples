// NGRX
import { createFeatureSelector, createSelector } from '@ngrx/store';
// Lodash
import { each } from 'lodash';
// CRUD
import { QueryResultsModel, HttpExtenstionsModel } from '../../_base/crud';
// State
import { ContactsState } from '../_reducers/contacts.reducers';
import { ContactsModel } from '../_models/contacts.model';

export const selectContactsState = createFeatureSelector<ContactsState>('contacts');

export const selectProductById = (contactId: number) => createSelector(
	selectContactsState,
	contactsState => contactsState.entities[contactId]
);

export const selectContactsPageLoading = createSelector(
	selectContactsState,
	contactsState => contactsState.listLoading
);

export const selectContactsActionLoading = createSelector(
	selectContactsState,
	customersState => customersState.actionsloading
);

export const selectContactsPageLastQuery = createSelector(
	selectContactsState,
	contactsState => contactsState.lastQuery
);

export const selectLastCreatedProductId = createSelector(
	selectContactsState,
	contactsState => contactsState.lastCreatedProductId
);

export const selectContactsInitWaitingMessage = createSelector(
	selectContactsState,
	contactsState => contactsState.showInitWaitingMessage
);

export const selectContactsInStore = createSelector(
	selectContactsState,
	contactsState => {
		const items: ProductModel[] = [];
		each(contactsState.entities, element => {
			items.push(element);
		});
		const httpExtension = new HttpExtenstionsModel();
		const result: ProductModel[] = httpExtension.sortArray(items, contactsState.lastQuery.sortField, contactsState.lastQuery.sortOrder);
		return new QueryResultsModel(result, contactsState.totalCount, '');
	}
);

export const selectHasContactsInStore = createSelector(
	selectContactsInStore,
	queryResult => {
		if (!queryResult.totalCount) {
			return false;
		}

		return true;
	}
);
