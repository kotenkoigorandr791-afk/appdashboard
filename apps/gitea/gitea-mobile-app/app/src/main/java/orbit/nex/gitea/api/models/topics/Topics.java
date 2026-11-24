package orbit.nex.gitea.api.models.topics;

import com.google.gson.annotations.SerializedName;
import java.io.Serializable;
import java.util.List;

public class Topics implements Serializable {

	@SerializedName("topics")
	private List<String> topics;

	public List<String> getTopics() {
		return topics;
	}
}
