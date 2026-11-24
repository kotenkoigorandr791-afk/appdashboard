package orbit.nex.gitea.fragments;

import android.app.Dialog;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.lifecycle.ViewModelProvider;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.bottomsheet.BottomSheetDialogFragment;
import java.util.ArrayList;
import java.util.Objects;
import orbit.nex.gitea.R;
import orbit.nex.gitea.activities.ExploreActivity;
import orbit.nex.gitea.adapters.UsersAdapter;
import orbit.nex.gitea.databinding.BottomsheetExploreUsersSearchBinding;
import orbit.nex.gitea.databinding.FragmentExploreUsersBinding;
import orbit.nex.gitea.helpers.AppUtil;
import orbit.nex.gitea.helpers.Constants;
import orbit.nex.gitea.helpers.EndlessRecyclerViewScrollListener;
import orbit.nex.gitea.helpers.Toasty;
import orbit.nex.gitea.helpers.UIHelper;
import orbit.nex.gitea.viewmodels.UserListViewModel;

public class ExploreUsersFragment extends Fragment
		implements ExploreActivity.ExploreActionInterface {

	private FragmentExploreUsersBinding binding;
	private UserListViewModel viewModel;
	private UsersAdapter adapter;
	private EndlessRecyclerViewScrollListener scrollListener;
	private int resultLimit;
	private String currentQuery = "";
	private boolean isFirstLoad = true;
	private boolean isViewReady = false;

	@Override
	public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
		super.onViewCreated(view, savedInstanceState);
		View dock = requireActivity().findViewById(R.id.docked_toolbar);
		UIHelper.applyInsets(view, dock, binding.recyclerView, binding.pullToRefresh, null);
		isViewReady = true;
	}

	@Override
	public View onCreateView(
			@NonNull LayoutInflater inflater, ViewGroup container, Bundle savedInstanceState) {
		binding = FragmentExploreUsersBinding.inflate(inflater, container, false);
		resultLimit = Constants.getCurrentResultLimit(requireContext());
		viewModel = new ViewModelProvider(this).get(UserListViewModel.class);

		setupRecyclerView();
		setupSwipeRefresh();
		observeViewModel();

		return binding.getRoot();
	}

	@Override
	public void onResume() {
		super.onResume();
		if (!isHidden() && isFirstLoad && isViewReady) {
			lazyLoad();
		}
	}

	@Override
	public void onHiddenChanged(boolean hidden) {
		super.onHiddenChanged(hidden);
		if (!hidden && isFirstLoad && isViewReady) {
			lazyLoad();
		}
	}

	private void lazyLoad() {
		isFirstLoad = false;
		refreshData(currentQuery);
	}

	@Override
	public void onSearchTriggered() {
		showSearchBottomSheet();
	}

	private void setupRecyclerView() {
		adapter = new UsersAdapter(requireContext(), new ArrayList<>());
		LinearLayoutManager layoutManager = new LinearLayoutManager(requireContext());
		binding.recyclerView.setLayoutManager(layoutManager);
		binding.recyclerView.setAdapter(adapter);

		scrollListener =
				new EndlessRecyclerViewScrollListener(layoutManager) {
					@Override
					public void onLoadMore(int page, int totalItemsCount, RecyclerView view) {
						viewModel.fetchUsers(
								requireContext(),
								"explore",
								null,
								null,
								currentQuery,
								page,
								resultLimit,
								false);
					}
				};
		binding.recyclerView.addOnScrollListener(scrollListener);
	}

	private void observeViewModel() {
		viewModel
				.getUsers()
				.observe(
						getViewLifecycleOwner(),
						list -> {
							adapter.updateList(list);
							updateUiVisibility(
									Boolean.TRUE.equals(viewModel.getIsLoading().getValue()));
						});

		viewModel.getIsLoading().observe(getViewLifecycleOwner(), this::updateUiVisibility);

		viewModel
				.getError()
				.observe(
						getViewLifecycleOwner(),
						error -> {
							if (error != null) Toasty.show(requireContext(), error);
						});
	}

	private void updateUiVisibility(boolean isLoading) {
		boolean hasData = adapter.getItemCount() > 0;
		binding.expressiveLoader.setVisibility(isLoading && !hasData ? View.VISIBLE : View.GONE);

		boolean hasLoadedOnce = Boolean.TRUE.equals(viewModel.getHasLoadedOnce().getValue());
		binding.layoutEmpty
				.getRoot()
				.setVisibility(!isLoading && !hasData && hasLoadedOnce ? View.VISIBLE : View.GONE);
		binding.pullToRefresh.setVisibility(
				!hasData && !isLoading && hasLoadedOnce ? View.GONE : View.VISIBLE);
	}

	private void refreshData(String query) {
		currentQuery = query;
		if (scrollListener != null) scrollListener.resetState();
		if (viewModel == null) return;
		viewModel.resetPagination();
		binding.expressiveLoader.setVisibility(View.VISIBLE);
		viewModel.fetchUsers(requireContext(), "explore", null, null, query, 1, resultLimit, true);
	}

	private void setupSwipeRefresh() {
		binding.pullToRefresh.setOnRefreshListener(
				() -> {
					binding.pullToRefresh.setRefreshing(false);
					refreshData(currentQuery);
				});
	}

	private void showSearchBottomSheet() {
		SearchUserBottomSheet sheet =
				SearchUserBottomSheet.newInstance(
						currentQuery,
						new SearchUserBottomSheet.SearchCallback() {
							@Override
							public void onSearchApplied(String query) {
								refreshData(query);
							}

							@Override
							public void onReset() {
								refreshData("");
							}
						});
		sheet.show(getChildFragmentManager(), "UserSearchSheet");
	}

	public static class SearchUserBottomSheet extends BottomSheetDialogFragment {

		private SearchCallback callback;
		private BottomsheetExploreUsersSearchBinding sheetBinding;

		public interface SearchCallback {
			void onSearchApplied(String query);

			void onReset();
		}

		public static SearchUserBottomSheet newInstance(String query, SearchCallback cb) {
			SearchUserBottomSheet fragment = new SearchUserBottomSheet();
			Bundle args = new Bundle();
			args.putString("q", query);
			fragment.setArguments(args);
			fragment.callback = cb;
			return fragment;
		}

		@NonNull @Override
		public Dialog onCreateDialog(@Nullable Bundle savedInstanceState) {
			BottomSheetDialog dialog = (BottomSheetDialog) super.onCreateDialog(savedInstanceState);
			AppUtil.applySheetStyle(dialog, true);
			return dialog;
		}

		@Nullable @Override
		public View onCreateView(
				@NonNull LayoutInflater inflater,
				@Nullable ViewGroup container,
				@Nullable Bundle savedInstanceState) {
			sheetBinding = BottomsheetExploreUsersSearchBinding.inflate(inflater, container, false);

			if (getArguments() != null) {
				sheetBinding.searchQueryEdit.setText(getArguments().getString("q", ""));
			}

			setupValidation();

			sheetBinding.btnApply.setOnClickListener(
					v -> {
						if (callback != null) {
							callback.onSearchApplied(
									Objects.requireNonNull(sheetBinding.searchQueryEdit.getText())
											.toString()
											.trim());
						}
						dismiss();
					});

			sheetBinding.btnClear.setOnClickListener(
					v -> {
						if (callback != null) {
							callback.onReset();
						}
						dismiss();
					});

			return sheetBinding.getRoot();
		}

		private void setupValidation() {
			validate();
			sheetBinding.searchQueryEdit.addTextChangedListener(
					new TextWatcher() {
						@Override
						public void beforeTextChanged(
								CharSequence s, int start, int count, int after) {}

						@Override
						public void onTextChanged(
								CharSequence s, int start, int before, int count) {
							validate();
						}

						@Override
						public void afterTextChanged(Editable s) {}
					});
		}

		private void validate() {
			String query =
					Objects.requireNonNull(sheetBinding.searchQueryEdit.getText())
							.toString()
							.trim();
			sheetBinding.btnApply.setEnabled(!query.isEmpty());
		}

		@Override
		public void onStart() {
			super.onStart();
			if (getDialog() instanceof BottomSheetDialog) {
				AppUtil.applySheetStyle((BottomSheetDialog) getDialog(), true);
			}
		}

		@Override
		public void onDestroyView() {
			super.onDestroyView();
			sheetBinding = null;
		}
	}

	@Override
	public void onDestroyView() {
		super.onDestroyView();
		binding = null;
	}
}
