package orbit.nex.gitea.activities;

import android.os.Bundle;
import orbit.nex.gitea.R;
import orbit.nex.gitea.databinding.ActivityCommitDetailsBinding;
import orbit.nex.gitea.fragments.CommitDetailFragment;

/**
 * @author qwerty287
 */
public class CommitDetailActivity extends BaseActivity {

	@Override
	public void onCreate(Bundle savedInstanceState) {
		super.onCreate(savedInstanceState);

		ActivityCommitDetailsBinding binding =
				ActivityCommitDetailsBinding.inflate(getLayoutInflater());
		setContentView(binding.getRoot());

		if (savedInstanceState == null) {
			getSupportFragmentManager()
					.beginTransaction()
					.replace(R.id.fragment_container, CommitDetailFragment.newInstance())
					.commit();
		}
	}
}
