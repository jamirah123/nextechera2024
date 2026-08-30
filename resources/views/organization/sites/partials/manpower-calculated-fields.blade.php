<div class="field">
    <label class="field__label">Total guards</label>
    <output class="field__control field__control--readonly" x-text="totalGuards()">0</output>
    <input type="hidden" name="required_guards" :value="totalGuards()">
    <p class="field__help">Day + night guards.</p>
</div>

<div class="field">
    <label class="field__label">Number of posts</label>
    <output class="field__control field__control--readonly" x-text="postCount()">0</output>
    <input type="hidden" name="number_of_posts" :value="postCount()">
    <p class="field__help">Highest shift requirement (physical posts).</p>
</div>
