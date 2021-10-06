wp.blocks.registerBlockType("testkwaplugin/campus-press-test", {
  title: "Test KWA Plugin",
  icon: "smiley",
  category: "common",
  attributes: {
    movieTitleSearch: {
      type: "string"
    },
  },
  edit: function ({ attributes, setAttributes }) {
    const { movieTitleSearch } = attributes;

    function updateMovieTitleSearch(event) {
      setAttributes({ movieTitleSearch: event.target.value })
    }

    return (
      <div>
        <input type="text" placeholder="Lookup Movie" value={movieTitleSearch} onChange={updateMovieTitleSearch} />
      </div>
    )
  },
  save: function (props) {
    return null
  }
})
